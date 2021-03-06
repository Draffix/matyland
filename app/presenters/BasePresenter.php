<?php

use Nette\Application\UI,
    Nette\Security as NS;
use Nette\Diagnostics\Debugger;

/**
 * Base presenter společný pro všechny presentery
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter {

    /** @var ProductModel */
    protected $mainProduct;

    /** @var CategoryModel */
    protected $category;

    /** @var BasketModel */
    protected $basket;

    /** @var UserModel */
    protected $users;

    /** @var DeliveryPaymentModel */
    protected $deliveryPayment;

    /** @var SettingModel */
    protected $setting;

    /** @var EmailTemplateModel */
    protected $emailTemplate;

    /** @var SliderModel */
    protected $slider;

    /* zaregistruji si všechny potřebné služby společné pro všechny presentery
     * "odstartuji" session
     * Vložení služby do modelu z configu 
     */

    protected function startup() {
        parent::startup();

        AntispamControl::register();

        $this->mainProduct = $this->context->product;
        $this->category = $this->context->category;
        $this->basket = $this->context->basket;
        $this->users = $this->context->users;
        $this->deliveryPayment = $this->context->deliveryPayment;
        $this->setting = $this->context->setting;
        $this->emailTemplate = $this->context->emailTemplate;
        $this->slider = $this->context->slider;

        // zahájíme session a potlačíme E_NOTICE při znovu zavolání startupu
        @session_start();
    }

    public function beforeRender() {
        $this->template->setting = $this->setting->fetchAllSettings();

        $this->template->usersData = $this->users->find($this->getUser()->getId()); //v templatech mám k dispozici všechny údaje z přihlášeného ID
        $this->template->categories = $this->category->getSubtree(1);
        $this->template->randomProduct = $this->mainProduct->randomProduct();
        $this->template->id = $this->getParameter('id');

        $this->template->detail = $this->category->pks($this->getParameter('id'));

        if (!isset($_SESSION["totalPrice"])) {
            $_SESSION["totalPrice"] = 0;
        }

        if (!isset($_SESSION["count"])) {
            $_SESSION["count"] = 0;
        }

        Debugger::barDump($_SESSION);
    }

    /* signál pro odhlášení uživatele */

    public function handleSignOut() {
        $this->getUser()->logout(TRUE); //odhlásí i identitu
        $_SESSION["totalPrice"] = 0;
        $_SESSION["count"] = 0;
        unset($_SESSION["cart"]); // aby nám nic nezbylo v košíku
        $this->redirect('Homepage:default');
    }

    /* signál pro přidání položky do košíku
     * do parametru $quantity se automaticky předává 1 
     */

    public function handleAddCart($id, $quantity) {
        if (!isset($_SESSION["cart"][$id])) {   // pokud neexistuje id produktu v košíku
            $_SESSION["cart"][$id] = $this->basket->fetchImagesAndAll($id); //zjisti všechny informace o produktu
            // pokud je zvolena sleva tak vypočítám cenu z formule pro slevu
            if ($this->setting->fetchAllSettings()->eshop_discount > 0) {
                $_SESSION["cart"][$id]["prod_price"] = round($_SESSION["cart"][$id]["prod_price"] -
                        ($_SESSION["cart"][$id]["prod_price"] * ($this->setting->fetchAllSettings()->eshop_discount / 100)));
            }
            $_SESSION["cart"][$id]["basket_quantity"] = $quantity; // do množství připiš jedničku
            $_SESSION["cart"][$id]["totalPrice"] = $_SESSION["cart"][$id]["prod_price"]; // celková cena produktu jakožto jeden produkt
        } else {
            $_SESSION["cart"][$id]["basket_quantity"] += $quantity; // když id existuje přičti jedničku k množství
            $_SESSION["cart"][$id]["totalPrice"] = $_SESSION["cart"][$id]["basket_quantity"] * $_SESSION["cart"][$id]["prod_price"]; // celková cena jednoho produktu
        }

        // přičítám položky do košíku přes globální session
        $_SESSION["count"] += 1;

        // přičítám cenu jednoho výrobku ke globální session
        $_SESSION["totalPrice"] += $_SESSION["cart"][$id]["prod_price"];

        // získání informací o uživateli a produktu (pouze pokud je přihlášen)
        if ($this->getUser()->isLoggedIn()) {
            $s = session_id();
            $u = $this->getUser()->getId();
            $ip = $_SERVER['REMOTE_ADDR'];
            $q = $quantity;
            $product = $id;

            // uložení údajů do pole
            $items = array('basket_session_id' => $s, 'user_id' => $u, 'basket_ip_address' => $ip,
                'basket_quantity' => $q);

            // zjišťujeme, jestli v košíku uživatele už existuje produkt
            if ($this->basket->findProduct($id, $u) == 0) { // pokud ne, tak ho uložíme
                $this->basket->saveItemIntoBasket($items, $product); // uložení do tabulky 'basket'
            } else {
                $q = $this->basket->findQuantity($id); // zjistění množství jednoho produktu v košíku
                $quantum = $q->quan + $quantity; // přidání nově zvoleného množství
                $this->basket->updateProduct($id, $quantum); // update množství k danému ID produktu
            }
        }

        $this->presenter->redirect('Basket:');
    }

    protected function createComponentHledej() {
        $form = new UI\Form;
        $form->setMethod('GET');

        $form->addText('search')
                ->addRule($form::FILLED);
        $form->addButton('btnSearch', 'Hledat');
        $form->onSuccess[] = callback($this, 'hledejSubmitted');
        return $form;
    }

    public function hledejSubmitted(UI\Form $form) {
        $values = $form->getValues();
        unset($values['btnSearch']);
        $this->redirect('Search:s', $values->search);
    }

    public function createComponentNabidkaForm() {
        $form = new Nette\Application\UI\Form;
        $form->addText('price');
        $form->addSubmit('send');
        $form->onSuccess[] = callback($this, 'nabidkaFormSubmitted');
        return $form;
    }

    public function nabidkaFormSubmitted(Nette\Application\UI\Form $form) {
        $values = $form->getValues();
        preg_match('/(?P<min_price>.*)\s*Kč\s*-\s*(?P<max_price>.*)\s*Kč/', $values["price"], $matches); //získáme min a max cenu
        $matches['min_price'] = preg_replace('/\s+/', '', $matches['min_price']); // odstraníme whitespace
        $matches['max_price'] = preg_replace('/\s+/', '', $matches['max_price']); // odstraníme whitespace
        $this->redirect('Search:filtr', $matches['min_price'], $matches['max_price']);
    }
    
        public function templatePrepareFilters($template) {
        $template->registerFilter($latte = $this->context->nette->createLatte());

        $set = Nette\Latte\Macros\MacroSet::install($latte->getCompiler());
        $set->addMacro('ifCurrentIn', function($node, $writer) {
                    return $writer->write('foreach (%node.array as $l) { if ($_presenter->isLinkCurrent($l)) { $_c = true; break; }} if (isset($_c)): ');
                }, 'endif; unset($_c);');
    }

}
