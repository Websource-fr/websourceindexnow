<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class WebsourceIndexnow extends Module
{
    protected $engines = [
        'bing' => 'Bing (api.indexnow.org) [recommandé]',
        'google' => 'Google (via Indexing API)',
    ];

    protected $engine_endpoints = [
        'bing' => 'https://api.indexnow.org/IndexNow',
    ];

    public function __construct()
    {
        $this->name = 'websourceindexnow';
        $this->tab = 'front_office_features';
        $this->version = '1.2.0';
        $this->author = 'Websource';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Websource IndexNow');
        $this->description = $this->l('Soumettez instantanément vos URLs à Bing et Google.');
        $this->confirmUninstall = $this->l('Êtes-vous sûr de vouloir désinstaller ce module ?');
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];

    }

    private function installAdminTab($class_name, $name)
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = $class_name;
        $tab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $name;
        }
        $tab->id_parent = (int)Tab::getIdFromClassName('IMPROVE'); // ou 0 pour racine
        $tab->module = $this->name;
        $tab->add();
    }

    public function install()
    {
        Configuration::updateValue('WEBSOURCEINDEXNOW_ENGINES', implode(',', array_keys($this->engines)));
        Configuration::updateValue('WEBSOURCEINDEXNOW_AUTO_CMS', 1);
        Configuration::updateValue('WEBSOURCEINDEXNOW_AUTO_CATEGORY', 1);
        Configuration::updateValue('WEBSOURCEINDEXNOW_AUTO_PRODUCT', 1);
        Configuration::updateValue('WEBSOURCEINDEXNOW_AUTO_BRAND', 1);
        Configuration::updateValue('WEBSOURCEINDEXNOW_AUTO_UNIQUE', 1);
        $this->installAdminTab('AdminWebsourceindexnowBulkurls', 'Envoi URLs IndexNow');

        return parent::install()
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('actionProductSave')
            && $this->registerHook('actionCategorySave')
            && $this->registerHook('actionCmsPageSave')
            && $this->registerHook('actionManufacturerSave');
    }

    public function uninstall()
    {
        $keys = [
            'WEBSOURCEINDEXNOW_KEY',
            'WEBSOURCEINDEXNOW_ENGINES',
            'WEBSOURCEINDEXNOW_AUTO_CMS',
            'WEBSOURCEINDEXNOW_AUTO_CATEGORY',
            'WEBSOURCEINDEXNOW_AUTO_PRODUCT',
            'WEBSOURCEINDEXNOW_AUTO_BRAND',
            'WEBSOURCEINDEXNOW_AUTO_UNIQUE',
            'WEBSOURCEINDEXNOW_GOOGLE_CLIENT_ID',
            'WEBSOURCEINDEXNOW_GOOGLE_CLIENT_SECRET',
            'WEBSOURCEINDEXNOW_GOOGLE_REFRESH_TOKEN',
        ];
        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }
        return parent::uninstall();
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitAddconfiguration')) {
            $this->postProcess();
        }
        if (Tools::isSubmit('submitIndexnowManual')) {
            $this->postProcessManual();
        }

        $use_ssl = (bool)Configuration::get('PS_SSL_ENABLED');
        $domain = $use_ssl ? Configuration::get('PS_SHOP_DOMAIN_SSL') : Configuration::get('PS_SHOP_DOMAIN');
        $scheme = $use_ssl ? 'https' : 'http';
        $shop_url = $scheme . '://' . $domain . '/';

        // Obtenez le schéma et le domaine
        $use_ssl = Configuration::get('PS_SSL_ENABLED');
        $domain = $use_ssl ? Configuration::get('PS_SHOP_DOMAIN_SSL') : Configuration::get('PS_SHOP_DOMAIN');
        $scheme = $use_ssl ? 'https://' : 'http://';

        $controllerName = 'AdminWebsourceindexnowGoogleredirect';
        $id_tab = Tab::getIdFromClassName($controllerName);

        if (!$id_tab) {
            $tab = new Tab();
            $tab->active = 0; // caché dans le menu
            $tab->class_name = $controllerName;
            $tab->module = $this->name;
            $tab->id_parent = (int)Tab::getIdFromClassName('IMPROVE'); // ou 0 pour racine
            foreach (Language::getLanguages(false) as $lang) {
                $tab->name[$lang['id_lang']] = 'Google Redirect';
            }
            $tab->add();
        }

        $this->context->smarty->assign([
            'module_dir' => $this->_path,
            'shop_url' => $shop_url,
            'current_url' => $this->context->link->getAdminLink('AdminWebsourceindexnowGoogleredirect', false)
        ]);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $key = Configuration::get('WEBSOURCEINDEXNOW_KEY');
        $fileExists = $key && file_exists(_PS_ROOT_DIR_ . '/' . $key . '.txt');
        $use_ssl = (bool)Configuration::get('PS_SSL_ENABLED');
        $domain = $use_ssl ? Configuration::get('PS_SHOP_DOMAIN_SSL') : Configuration::get('PS_SHOP_DOMAIN');
        $scheme = $use_ssl ? 'https' : 'http';
        $key_url = $key ? $scheme . '://' . $domain . '/' . $key . '.txt' : '';

        $checkbox_values = [];
        foreach ($this->engines as $id => $name) {
            $checkbox_values[] = ['id' => $id, 'name' => $name];
        }

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configuration IndexNow'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Clé API IndexNow'),
                        'name' => 'WEBSOURCEINDEXNOW_KEY',
                        'required' => true,
                        'desc' => $this->l('Générez une clé sur indexnow.org et copiez-la ici.') .
                            ($key ? '<br/><strong>' . $this->l('URL du fichier clé IndexNow : ') . '<a href="' . $key_url . '" target="_blank">' . $key_url . '</a></strong>' : ''),
                    ],
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Moteurs de recherche cibles'),
                        'name' => 'WEBSOURCEINDEXNOW_ENGINES',
                        'desc' => $this->l('Sélectionnez un ou plusieurs moteurs de recherche pour la soumission des URLs.'),
                        'values' => [
                            'query' => $checkbox_values,
                            'id' => 'id',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Client ID Google'),
                        'name' => 'WEBSOURCEINDEXNOW_GOOGLE_CLIENT_ID',
                        'required' => false,
                        'desc' => $this->l('Entrez votre Client ID Google pour l\'authentification OAuth 2.0.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Client Secret Google'),
                        'name' => 'WEBSOURCEINDEXNOW_GOOGLE_CLIENT_SECRET',
                        'required' => false,
                        'desc' => $this->l('Entrez votre Client Secret Google pour l\'authentification OAuth 2.0.'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Envoyer automatiquement les pages CMS'),
                        'name' => 'WEBSOURCEINDEXNOW_AUTO_CMS',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Non')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Envoyer automatiquement les catégories'),
                        'name' => 'WEBSOURCEINDEXNOW_AUTO_CATEGORY',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Non')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Envoyer automatiquement les produits'),
                        'name' => 'WEBSOURCEINDEXNOW_AUTO_PRODUCT',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Non')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Envoyer automatiquement les marques'),
                        'name' => 'WEBSOURCEINDEXNOW_AUTO_BRAND',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Non')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Envoyer automatiquement les URLs uniques'),
                        'name' => 'WEBSOURCEINDEXNOW_AUTO_UNIQUE',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Non')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Enregistrer'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->fields_value = $this->getConfigFormValues();

        $html = $helper->generateForm([$fields_form]);

        if ($key) {
            $html .= '<p>' . $this->l('Fichier clé :') . ' <strong><a href="' . $key_url . '" target="_blank">' . $key_url . '</a></strong> ';
            $html .= $fileExists ? '<span style="color:green">✔</span>' : '<span style="color:red">✖</span>';
            $html .= '</p>';
        }

        return $html;
    }

    protected function getConfigFormValues()
    {
        $id_shop = Shop::isFeatureActive() ? (int)Shop::getContextShopID() : null;
        $engines = explode(',', Configuration::get('WEBSOURCEINDEXNOW_ENGINES', implode(',', array_keys($this->engines)), null, $id_shop));

        $values = [];
        foreach ($this->engines as $engine => $label) {
            $values['WEBSOURCEINDEXNOW_ENGINES_'.$engine] = in_array($engine, $engines);
        }

        return array_merge([
            'WEBSOURCEINDEXNOW_KEY' => Configuration::get('WEBSOURCEINDEXNOW_KEY', '', null, $id_shop),
            'WEBSOURCEINDEXNOW_AUTO_CMS' => Configuration::get('WEBSOURCEINDEXNOW_AUTO_CMS', 1, null, $id_shop),
            'WEBSOURCEINDEXNOW_AUTO_CATEGORY' => Configuration::get('WEBSOURCEINDEXNOW_AUTO_CATEGORY', 1, null, $id_shop),
            'WEBSOURCEINDEXNOW_AUTO_PRODUCT' => Configuration::get('WEBSOURCEINDEXNOW_AUTO_PRODUCT', 1, null, $id_shop),
            'WEBSOURCEINDEXNOW_AUTO_BRAND' => Configuration::get('WEBSOURCEINDEXNOW_AUTO_BRAND', 1, null, $id_shop),
            'WEBSOURCEINDEXNOW_AUTO_UNIQUE' => Configuration::get('WEBSOURCEINDEXNOW_AUTO_UNIQUE', 1, null, $id_shop),
            'WEBSOURCEINDEXNOW_GOOGLE_CLIENT_ID' => Configuration::get('WEBSOURCEINDEXNOW_GOOGLE_CLIENT_ID', '', null, $id_shop),
            'WEBSOURCEINDEXNOW_GOOGLE_CLIENT_SECRET' => Configuration::get('WEBSOURCEINDEXNOW_GOOGLE_CLIENT_SECRET', '', null, $id_shop),
            'WEBSOURCEINDEXNOW_GOOGLE_REFRESH_TOKEN' => Configuration::get('WEBSOURCEINDEXNOW_GOOGLE_REFRESH_TOKEN', '', null, $id_shop),
        ], $values);
    }

    public function postProcessManual()
    {
        $manualUrl = Tools::getValue('indexnow_manual_url');

        // Vérifiez si une URL a été soumise
        if (empty($manualUrl)) {
            $this->context->smarty->assign('indexnow_manual_result', "<div class='error'>" . $this->l('Veuillez entrer une URL.') . "</div>");
            return;
        }

        // Validez l'URL
        if (filter_var($manualUrl, FILTER_VALIDATE_URL)) {
            $result = $this->sendIndexnowUrl($manualUrl);
            $this->context->smarty->assign('indexnow_manual_result', $result);
        } else {
            $this->context->smarty->assign('indexnow_manual_result', "<div class='error'>" . $this->l('URL invalide.') . "</div>");
        }
    }

    protected function postProcess()
    {
        $id_shop = Shop::isFeatureActive() ? (int)Shop::getContextShopID() : null;

        $engines = [];
        foreach ($this->engines as $engine => $label) {
            if (Tools::getValue('WEBSOURCEINDEXNOW_ENGINES_'.$engine)) {
                $engines[] = $engine;
            }
        }
        Configuration::updateValue('WEBSOURCEINDEXNOW_ENGINES', implode(',', $engines), false, null, $id_shop);

        $fields = [
            'WEBSOURCEINDEXNOW_KEY',
            'WEBSOURCEINDEXNOW_AUTO_CMS',
            'WEBSOURCEINDEXNOW_AUTO_CATEGORY',
            'WEBSOURCEINDEXNOW_AUTO_PRODUCT',
            'WEBSOURCEINDEXNOW_AUTO_BRAND',
            'WEBSOURCEINDEXNOW_AUTO_UNIQUE',
            'WEBSOURCEINDEXNOW_GOOGLE_CLIENT_ID',
            'WEBSOURCEINDEXNOW_GOOGLE_CLIENT_SECRET',
        ];

        foreach ($fields as $field) {
            Configuration::updateValue($field, Tools::getValue($field), false, null, $id_shop);
        }

        $key = Tools::getValue('WEBSOURCEINDEXNOW_KEY');
        if ($key && preg_match('/^[a-zA-Z0-9\-]{8,128}$/', $key)) {
            $filePath = _PS_ROOT_DIR_ . '/' . $key . '.txt';
            file_put_contents($filePath, $key);
        }

        $clientId = Tools::getValue('WEBSOURCEINDEXNOW_GOOGLE_CLIENT_ID');
        $clientSecret = Tools::getValue('WEBSOURCEINDEXNOW_GOOGLE_CLIENT_SECRET');

        if ($clientId && $clientSecret) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminWebsourceindexnowGoogleredirect', true));
        }
    }

    protected function exchangeCodeForRefreshToken($code)
    {
        $id_shop = Shop::isFeatureActive() ? (int)Shop::getContextShopID() : null;
        $clientId = Configuration::get('WEBSOURCEINDEXNOW_GOOGLE_CLIENT_ID', null, null, $id_shop);
        $clientSecret = Configuration::get('WEBSOURCEINDEXNOW_GOOGLE_CLIENT_SECRET', null, null, $id_shop);

        $use_ssl = (bool)Configuration::get('PS_SSL_ENABLED');
        $domain = $use_ssl ? Configuration::get('PS_SHOP_DOMAIN_SSL') : Configuration::get('PS_SHOP_DOMAIN');
        $scheme = $use_ssl ? 'https' : 'http';
        $shop_url = $scheme . '://' . $domain . '/';

        // Obtenez le schéma et le domaine
        $use_ssl = Configuration::get('PS_SSL_ENABLED');
        $domain = $use_ssl ? Configuration::get('PS_SHOP_DOMAIN_SSL') : Configuration::get('PS_SHOP_DOMAIN');
        $scheme = $use_ssl ? 'https://' : 'http://';

        // Obtenez le chemin d'accès actuel
        $requestUri = $_SERVER['REQUEST_URI'];

        // Construisez l'URL actuelle
        $currentUrl = $scheme . $domain . $requestUri;

        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $data = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $currentUrl,
            'grant_type' => 'authorization_code',
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        curl_close($ch);

        $responseData = json_decode($response, true);
        if (isset($responseData['refresh_token'])) {
            Configuration::updateValue('WEBSOURCEINDEXNOW_GOOGLE_REFRESH_TOKEN', $responseData['refresh_token'], false, null, $id_shop);
        }
    }

    public function getGoogleAccessToken()
    {
        $id_shop = Shop::isFeatureActive() ? (int)Shop::getContextShopID() : null;
        $clientId = Configuration::get('WEBSOURCEINDEXNOW_GOOGLE_CLIENT_ID', null, null, $id_shop);
        $clientSecret = Configuration::get('WEBSOURCEINDEXNOW_GOOGLE_CLIENT_SECRET', null, null, $id_shop);
        $refreshToken = Configuration::get('WEBSOURCEINDEXNOW_GOOGLE_REFRESH_TOKEN', null, null, $id_shop);

        if (!$clientId || !$clientSecret || !$refreshToken) {
            return null;
        }

        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $data = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            return null;
        }

        $responseData = json_decode($response, true);
        return $responseData['access_token'] ?? null;
    }

    protected function sendGoogleIndexingApiRequest($url, $type)
    {
        $accessToken = $this->getGoogleAccessToken();

        if (!$accessToken) {
            return "<div class='error'>Impossible d'obtenir un jeton d'accès OAuth 2.0.</div>";
        }

        $endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
        $data = [
            'url' => $url,
            'type' => $type,
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return "<div class='error'>Erreur CURL : " . htmlspecialchars($error) . "</div>";
        } elseif ($httpCode === 200) {
            return "<div class='success'>Succès, URL envoyée à Google.</div>";
        } else {
            return "<div class='error'>Erreur HTTP ($httpCode) : " . htmlspecialchars($response) . "</div>";
        }
    }

    public function sendIndexnowUrl($url)
    {
        $key = Configuration::get('WEBSOURCEINDEXNOW_KEY');
        if (!$key) {
            return "<div class='error'>Clé API manquante.</div>";
        }

        $engines = explode(',', Configuration::get('WEBSOURCEINDEXNOW_ENGINES', implode(',', array_keys($this->engines))));
        $use_ssl = (bool)Configuration::get('PS_SSL_ENABLED');
        $domain = $use_ssl ? Configuration::get('PS_SHOP_DOMAIN_SSL') : Configuration::get('PS_SHOP_DOMAIN');
        $scheme = $use_ssl ? 'https' : 'http';
        $keyLocation = $scheme . '://' . $domain . '/' . $key . '.txt';
        $host = parse_url($url, PHP_URL_HOST);

        $data = [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $keyLocation,
            'urlList' => [$url],
        ];

        $results = [];
        foreach ($engines as $engine) {
            if ($engine === 'google') {
                $this->sendGoogleIndexingApiRequest($url, 'URL_UPDATED');
                $results[] = $this->sendGoogleIndexingApiRequest($url, 'URL_UPDATED');
                continue;
            }
            if (!isset($this->engine_endpoints[$engine])) {
                continue;
            }
            $endpoint = $this->engine_endpoints[$engine];

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json; charset=utf-8'
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $label = ucfirst($engine);
            if ($error) {
                $results[] = "<div class='error'>$label : Erreur CURL : " . htmlspecialchars($error) . "</div>";
            } elseif ($httpCode === 200) {
                $results[] = "<div class='success'>$label : Succès, URL envoyée.</div>";
            } elseif ($httpCode === 202) {
                $results[] = "<div class='success'>$label : Succès partiel, URL acceptée et en attente de traitement.</div>";
            } elseif ($httpCode === 400) {
                $results[] = "<div class='error'>$label : Erreur 400, format de requête invalide.</div>";
            } elseif ($httpCode === 403) {
                $results[] = "<div class='error'>$label : Erreur 403, clé non valide ou fichier clé introuvable.</div>";
            } elseif ($httpCode === 422) {
                $results[] = "<div class='error'>$label : Erreur 422, URLs non valides pour ce domaine ou clé non conforme.</div>";
            } elseif ($httpCode === 429) {
                $results[] = "<div class='error'>$label : Erreur 429, trop de requêtes.</div>";
            } else {
                $results[] = "<div class='error'>$label : Erreur HTTP ($httpCode) : " . htmlspecialchars($response) . "</div>";
            }
        }

        return implode('', $results);
    }

    public function hookActionProductSave($params)
    {
        if (!Configuration::get('WEBSOURCEINDEXNOW_AUTO_PRODUCT')) {
            return;
        }
        $product = $params['object'];
        $url = $this->context->link->getProductLink($product);
        $this->sendIndexnowUrl($url);
    }

    public function hookActionCategorySave($params)
    {
        if (!Configuration::get('WEBSOURCEINDEXNOW_AUTO_CATEGORY')) {
            return;
        }
        $cat = $params['object'];
        $url = $this->context->link->getCategoryLink($cat);
        $this->sendIndexnowUrl($url);
    }

    public function hookActionCmsPageSave($params)
    {
        if (!Configuration::get('WEBSOURCEINDEXNOW_AUTO_CMS')) {
            return;
        }
        $cms = $params['object'];
        $url = $this->context->link->getCMSLink($cms);
        $this->sendIndexnowUrl($url);
    }

    public function hookActionManufacturerSave($params)
    {
        if (!Configuration::get('WEBSOURCEINDEXNOW_AUTO_BRAND')) {
            return;
        }
        $brand = $params['object'];
        $url = $this->context->link->getManufacturerLink($brand);
        $this->sendIndexnowUrl($url);
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }
}
