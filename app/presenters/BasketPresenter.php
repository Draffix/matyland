<?php

use Nette\Application\UI;

/**
 * Presenter pro nákupní košík
 */
class BasketPresenter extends BasePresenter {

    /**
     * @var ProductModel
     */
    protected $products;

    protected function startup() {
        parent::startup();
        $this->products = $this->context->product;
    }

    public function renderDefault() {
        if (isset($_SESSION["cart"])) {
            foreach (array_keys($_SESSION['cart']) as $key) {
                if ($this->products->countProductQuantity($key)->pocet == 0) {
                    $_SESSION['cart'][$key]['prod_on_stock'] = 0;
                }
            }
        }
    }

    public function handleRemoveFromCart($id) {
        $_SESSION["totalPrice"] -= ($_SESSION["cart"][$id]["basket_quantity"] * $_SESSION["cart"][$id]["prod_price"]);
        $_SESSION["count"] -= $_SESSION["cart"][$id]["basket_quantity"];
        unset($_SESSION["cart"][$id]);

        if ($this->getUser()->isLoggedIn()) {
            $this->basket->dropItemFromBasket($id, $this->getUser()->getId());
        }

        $this->presenter->redirect('this');
    }

    protected function createComponentUpdateForm() {
        $form = new UI\Form;
        $form->addText('quantity', 'Množství');
        $form->addHidden('product_id');
        $form->addSubmit('update', 'Upravit');
        $form->onSuccess[] = callback($this, 'updateFormSubmitted');
        return $form;
    }

    public function updateFormSubmitted($form) {
        $values = $form->getValues(); // získám všechny hodnoty z formuláře
// zkotrolujeme, zda máme tolik kusů na skladě. Pokud ne, přidáme do košíku
// tolik, kolik na skladě máme
        if ($this->products->countProductQuantity($_SESSION["cart"][$values->product_id]['prod_id'])->pocet < $values->quantity) {
            $values->quantity = $this->products->countProductQuantity($_SESSION["cart"][$values->product_id]['prod_id'])->pocet;
            $this->flashMessage('Bohužel, takové množství na skladě nemáme. 
                Do košíku bylo přidáno ' . $values->quantity . ' kusů', 'wrong');
        }

        $_SESSION["cart"][$values->product_id]["totalPrice"] = $values->quantity * $_SESSION["cart"][$values->product_id]["prod_price"];
// pokud je zvoleno množství 0:
// odečte se v count celkové množství daného produktu
// v totalPrice se provede celková suma -= množství daného produktu * cena jednoho produktu
// zrušíme v session pole daného produktu
        if ($values->quantity <= 0) {
            $_SESSION["count"] -= $_SESSION["cart"][$values->product_id]["basket_quantity"];
            $_SESSION["totalPrice"] -= ($_SESSION["cart"][$values->product_id]["basket_quantity"] * $_SESSION["cart"][$values->product_id]["prod_price"]);
            unset($_SESSION["cart"][$values->product_id]);
            if ($this->getUser()->isLoggedIn()) {
                $this->basket->dropItemFromBasket($values->product_id, $this->getUser()->getId());
            }
        }

// pokud je zvolené množství větší než stávající množství produktu:
// celkové množství += zadané množství - stávající množství produktu
// celková cena += cena jednoho produktu * (zadané množství - stávající množství)
// změníme množství produktu podle zadaného množství
        elseif ($values->quantity > $_SESSION["cart"][$values->product_id]["basket_quantity"]) {
// přičítám položky do košíku přes globální session
            $_SESSION["count"] += ($values->quantity - $_SESSION["cart"][$values->product_id]["basket_quantity"]);
            $_SESSION["totalPrice"] += $_SESSION["cart"][$values->product_id]["prod_price"] * ($values->quantity - $_SESSION["cart"][$values->product_id]["basket_quantity"]);
            $_SESSION["cart"][$values->product_id]["basket_quantity"] = $values->quantity;
        }

// pokud je zvolené množství menší než stávající množství produktu:
// celkové množství -= stávající množství produktu - zadané množství
// celková cena -= cena jednoho produktu * (stávající množství - zadané množství)
// změníme množství produktu podle zadaného množství
        elseif ($values->quantity < $_SESSION["cart"][$values->product_id]["basket_quantity"]) {
            $_SESSION["count"] -= ($_SESSION["cart"][$values->product_id]["basket_quantity"] - $values->quantity);
            $_SESSION["totalPrice"] -= $_SESSION["cart"][$values->product_id]["prod_price"] * ($_SESSION["cart"][$values->product_id]["basket_quantity"] - $values->quantity);
            $_SESSION["cart"][$values->product_id]["basket_quantity"] = $values->quantity;
        }

        if ($this->getUser()->isLoggedIn()) {
            if (isset($_SESSION["cart"][$values->product_id])) {
                $this->basket->updateProduct($values->product_id, $_SESSION["cart"][$values->product_id]["basket_quantity"]);
            }
        }

        $this->presenter->redirect('this');
    }

}