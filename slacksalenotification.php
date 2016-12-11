<?php

if (!defined('_PS_VERSION_'))
    exit;

class SlackSaleNotification extends Module
{
    public function __construct()
    {
        $this->name = 'slacksalenotification';
        $this->tab = 'administration';
        $this->version = '1.0';
        $this->author = 'Thomas Petracco';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_); 
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Slack Sale Notification');
        $this->description = $this->l('Send a notification to Slack when an order is paid.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        if (!parent::install()
            || !$this->setVariables()
            || !$this->registerHook('actionPaymentConfirmation')
        )
            return false;
        return true;
    }

    public function setVariables()
    {
        if (!Configuration::updateValue('SLACKSALENOTIF_URL', 'https://hooks.slack.com/services/')
            || !Configuration::updateValue('SLACKSALENOTIF_MESSAGE', 'New order')
            || !Configuration::updateValue('SLACKSALENOTIF_BOT', 'PrestaBot')
            || !Configuration::updateValue('SLACKSALENOTIF_ICON', ':tada:')
            || !Configuration::updateValue('SLACKSALENOTIF_PRICE', '1')
        )
        {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall())
            return false;
        return true;
    }

    public function hookActionPaymentConfirmation($params)
    {
        $webhook_url = Configuration::get('SLACKSALENOTIF_URL');
        $message = Configuration::get('SLACKSALENOTIF_MESSAGE');
        $bot = Configuration::get('SLACKSALENOTIF_BOT');
        $icon = Configuration::get('SLACKSALENOTIF_ICON');
        $price = Configuration::get('SLACKSALENOTIF_PRICE');

        if ($price == 1)
        {
            $cart = new Cart();
            $amount = $cart->getOrderTotalUsingTaxCalculationMethod($params['cart']->id);
            $message .= ' --- ' . $amount;
        }

        $payload = "payload=" . json_encode([
            "text" => $message,
            "username" => $bot,
            "icon_emoji" => $icon,
        ]);

        $ch = curl_init($webhook_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec ($ch);
        curl_close ($ch);
    }

    public function getContent()
    {
        $output = null;
     
        if (Tools::isSubmit('submit'.$this->name))
        {
            if (!Tools::getIsset('SLACKSALENOTIF_URL')
                || !Tools::getIsset('SLACKSALENOTIF_MESSAGE')
                || !Tools::getIsset('SLACKSALENOTIF_BOT')
                || !Tools::getIsset('SLACKSALENOTIF_ICON')
                || !Configuration::updateValue('SLACKSALENOTIF_URL', Tools::getValue('SLACKSALENOTIF_URL'))
                || !Configuration::updateValue('SLACKSALENOTIF_MESSAGE', Tools::getValue('SLACKSALENOTIF_MESSAGE'))
                || !Configuration::updateValue('SLACKSALENOTIF_BOT', Tools::getValue('SLACKSALENOTIF_BOT'))
                || !Configuration::updateValue('SLACKSALENOTIF_ICON', Tools::getValue('SLACKSALENOTIF_ICON'))
                || !Configuration::updateValue('SLACKSALENOTIF_PRICE', Tools::getValue('SLACKSALENOTIF_PRICE_on')))
            {
                $output .= $this->displayError($this->l('There was a problem updating the settings.'));
                return $output.$this->displayForm();
            }
        }
       
        $output .= $this->displayConfirmation($this->l('Settings updated'));
        return $output.$this->displayForm();
    }

    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
         
        // Init Fields form array
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Settings'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Slack WebHook URL'),
                    'name' => 'SLACKSALENOTIF_URL',
                    'size' => 200,
                    'required' => true,
                    'desc' => $this->l('Full URL to your Slack WebHook URL.'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Message'),
                    'name' => 'SLACKSALENOTIF_MESSAGE',
                    'size' => 200,
                    'required' => true,
                    'desc' => $this->l('The message you want to receive when a new order is paid.'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Bot name'),
                    'name' => 'SLACKSALENOTIF_BOT',
                    'size' => 50,
                    'required' => true,
                    'desc' => $this->l('The name of the bot.'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Bot icon'),
                    'name' => 'SLACKSALENOTIF_ICON',
                    'size' => 50,
                    'required' => true,
                    'desc' => $this->l('The icon of the bot. Example: :tada:'),
                ],
                [
                    'type' => 'checkbox',
                    'name' => 'SLACKSALENOTIF_PRICE',
                    'label' => $this->l('Show price'),
                    'is_bool' => true,
                    'values' => [
                        'query' => [
                            [
                                'id' => 'on',
                                'val' => '1',
                            ],
                        ],
                        'id' => 'id',
                        'name' => 'name',
                    ],
                    'desc' => $this->l('Display or not the price of the new order'),
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];
         
        $helper = new HelperForm();
         
        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
         
        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
         
        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = [
            'save' =>
            [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list'),
            ],
        ];

        $helper->tpl_vars = [
            'fields_value' => [
                'SLACKSALENOTIF_URL' => Configuration::get('SLACKSALENOTIF_URL'),
                'SLACKSALENOTIF_MESSAGE' => Configuration::get('SLACKSALENOTIF_MESSAGE'),
                'SLACKSALENOTIF_BOT' => Configuration::get('SLACKSALENOTIF_BOT'),
                'SLACKSALENOTIF_ICON' => Configuration::get('SLACKSALENOTIF_ICON'),
                'SLACKSALENOTIF_PRICE_on' => Configuration::get('SLACKSALENOTIF_PRICE'),
            ],
        ];
         
        return $helper->generateForm($fields_form);
    }
    
}