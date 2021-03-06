<?php

class CategoryModel extends Table {

    /** @var string */
    protected $tableName = 'category';

    public function categoryFilter($id, $limit, $offset) {
        return $this->connection->query(
                        'SELECT * FROM category, product, `category_has_product`, image 
                WHERE category.cat_id = category_has_product.category_cat_id 
                AND product.prod_id = category_has_product.product_prod_id
                AND product.prod_id = image.product_prod_id
                AND cat_id = ?
                AND product.prod_is_active = ?
                GROUP BY product.prod_id
                LIMIT ? OFFSET ?', $id, 1, $limit, $offset);
    }

    public function countCategoryFilter($id) {
        return $this->connection->query(
                        'SELECT COUNT(*) AS pocet
                        FROM category_has_product c JOIN product p ON product_prod_id = prod_id
                        WHERE p.prod_is_active = 1
                        AND `category_cat_id` = ?', $id)->fetch();
    }

    public function fetchAllCategoryNames() {
        return $this->connection->table('category')
                        ->select('cat_name')
                        ->select('cat_id');
    }

    public function insertProductIntoCategoryHasProduct($cat_id, $prod_id) {
        return $this->connection->table('category_has_product')
                        ->insert(array('category_cat_id' => $cat_id,
                            'product_prod_id' => $prod_id));
    }

    public function updateProductIntoCategoryHasProduct($old_cat_id, $new_cat_id, $prod_id) {
        return $this->connection->table('category_has_product')
                        ->where(array('category_cat_id' => $old_cat_id,
                            'product_prod_id' => $prod_id))
                        ->update(array('category_cat_id' => $new_cat_id,
                            'product_prod_id' => $prod_id));
    }

    /**
     * Smaže kategorii daného produktu
     * @param type $old_cat_id
     * @param type $prod_id
     * @return type
     */
    public function deleteProductIntoCategoryHasProduct($old_cat_id, $prod_id) {
        return $this->connection->table('category_has_product')
                        ->where(array('category_cat_id' => $old_cat_id,
                            'product_prod_id' => $prod_id))
                        ->delete();
    }

    /**
     * Smaže všechny kategorie daného produktu
     * @param type $prod_id
     * @return type
     */
    public function deleteAllProductIntoCategoryHasProduct($prod_id) {
        return $this->connection->table('category_has_product')
                        ->where(array('product_prod_id' => $prod_id))
                        ->delete();
    }

    public function fetchAllCategoryNamesForProduct($id) {
        return $this->connection->query('
                        SELECT p.prod_id, c.cat_name, cp.category_cat_id 
                        FROM category_has_product AS cp 
                        JOIN product AS p ON p.prod_id = cp.product_prod_id
                        JOIN category AS c ON c.cat_id = cp.category_cat_id
                        WHERE product_prod_id = ?', $id);
    }

    /**
     * Podle jména zjistí ID
     * @param type $name
     * @return type
     */
    public function findCategoryID($name) {
        return $this->getTable()
                        ->select('cat_id')
                        ->where('cat_name', $name);
    }

    /**
     * Zjistíme údaje o kategorii a kolik má položek - pro výpis kategorií
     * @return type
     */
    public function fetchAllCategoriesAndCountProducts() {
        return $this->connection->query('
                        SELECT c.cat_id, c.cat_name, COUNT(cp.product_prod_id) AS pocetPolozek
                        FROM category AS c
                        LEFT JOIN category_has_product AS cp ON cp.category_cat_id = c.cat_id
                        GROUP BY `cat_id`           
                        ');
    }

    /**
     * Změníme název kategorie
     * @param type $cat_id
     * @param type $cat_name
     * @return type
     */
    public function updateCategoryName($cat_id, $cat_name) {
        return $this->getTable()
                        ->where('cat_id', $cat_id)
                        ->update(array('cat_name' => $cat_name));
    }

    /**
     * Vymaže kategorii
     * @param type $cat_id
     * @return type
     */
    public function deleteCategory($cat_id) {
        return $this->getTable()
                        ->where('cat_id', $cat_id)
                        ->delete();
    }

    /**
     * Vytvoří kategorii
     * @param type $values
     * @return type
     */
    public function addCategory($values, $descendant) {
        $rowID = $this->getTable()->insert(array('cat_name' => $values->cat_name));
        $this->connection->query('
                    INSERT INTO category_closure (ancestor, descendant, depth) VALUES (?,?,0);', $rowID, $rowID);
        $this->connection->query('
            INSERT INTO category_closure (ancestor,descendant, depth)
            SELECT ancestor, ?, depth+1 FROM category_closure
            WHERE descendant = ?;', $rowID, $descendant);
    }

    /**
     * Celkový počet kategorií
     * @return type
     */
    public function countCategories() {
        return $this->getTable()
                        ->count();
    }

    public function findCategoryName($cat_id) {
        return $this->getTable()
                        ->where('cat_id', $cat_id)
                        ->select('cat_name')
                        ->fetch();
    }

    /**
     * Výpis stromu kategorie pro drobečkovou navigaci jednotlivé kategorie
     * @param type $id
     * @return type
     */
    public function toRootSubtree($id) {
        return $this->connection->query('
                    SELECT c.*, depth
                    FROM category c
                    JOIN category_closure cc
                      ON (c.cat_id = cc.ancestor)
                    WHERE cc.descendant = ?
                    ORDER BY depth DESC;', $id);
    }

    /**
     * Získáme všechny podkategorie daného předka a počet produktů podkategorie
     * @param type $ancestor
     * @return type
     */
    public function fetchAllAcestors($ancestor) {
        return $this->connection->query('
            SELECT c.*, cc.depth, COUNT(cp.product_prod_id) AS pocetPolozek
            FROM category c
            JOIN category_closure cc
            ON c.cat_id = cc.descendant
            LEFT JOIN category_has_product AS cp ON cp.category_cat_id = c.cat_id
            WHERE cc.ancestor = ?
            AND depth >0
            GROUP BY `cat_id`', $ancestor);
    }

    /**
     * Vrací všechny "kořeny" (nad nimi je pouze jeden hlavní kořen)
     * a kolik má položek - pro výpis kategorií
     * @return type
     */
    public function fetchAllRoots() {
        return $this->connection->query('
            SELECT c.*, cc.depth, COUNT(cp.product_prod_id) AS pocetPolozek
            FROM category c
            JOIN category_closure cc
            ON c.cat_id = cc.descendant
            LEFT JOIN category_has_product AS cp ON cp.category_cat_id = c.cat_id
            WHERE cc.ancestor = 1
            AND depth = 1
            GROUP BY `cat_id`');
    }

    /**
     * Získáme počet podkategorií předka
     * @param type $ancestor
     * @return type
     */
    public function countOfSubcategory($ancestor) {
        return $this->connection->query('
                    SELECT cc.*, c.*, COUNT(cat_id) AS pocet
                    FROM `category_closure` AS cc
                    JOIN category AS c ON cc.ancestor = c.cat_id
                    WHERE depth > 0
                    AND cat_id > 1
                    AND cc.ancestor = ?
                    GROUP BY cat_id', $ancestor);
    }

    /**
     * Slouží pro výpis a řazení kategorií
     * @param type $node
     * @return type
     */
    public function getSubtree($node) {
        $tree = $this->connection->query("
        SELECT c.*, cc2.ancestor, cc2.descendant, cc.depth
        FROM
            category c
            JOIN category_closure cc
            ON (c.cat_id = cc.descendant)
                JOIN category_closure cc2
                USING (descendant)
        WHERE cc.ancestor = $node AND cc2.depth = 1
        ORDER BY cc.depth, c.cat_id
    ");
        return $this->parseSubTree($node, $tree);
    }

    private function parseSubTree($rootID, $nodes) {
        // to allow direct access by node ID
        $byID = array();

        // an array of parrents and their children
        $byParent = array();


        foreach ($nodes as $node) {
            if ($node["cat_id"] != $rootID) {
                if (!isset($byParent[$node["ancestor"]])) {
                    $byParent[$node["ancestor"]] = array();
                }
                $byParent[$node["ancestor"]][] = $node["cat_id"];
            }
            $byID[$node["cat_id"]] = (array) $node;
        }

        // tree reconstruction
        $tree = array();
        foreach ($byParent[$rootID] as $nodeID) { // root direct children
            $tree[] = $this->parseChildren($nodeID, $byID, $byParent);
        }

        return $tree;
    }

    private function parseChildren($id, $nodes, $parents) {
        $tree = $nodes[$id];

        $tree["children"] = array();
        if (isset($parents[$id])) {
            foreach ($parents[$id] as $nodeID) {
                $tree["children"][] = $this->parseChildren($nodeID, $nodes, $parents);
            }
        }

        return $tree;
    }

    /***/
    public function pks($id) {
        return $this->connection->query('
                    SELECT cc.*, c.*
                    FROM `category_closure` AS cc
                    JOIN category AS c ON cc.ancestor = c.cat_id
                    WHERE descendant = ?
                    AND ancestor > 1
                    AND depth > 0', $id)
            ->fetch();
    }

}
