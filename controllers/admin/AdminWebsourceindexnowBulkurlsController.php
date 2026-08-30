<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminWebsourceindexnowBulkurlsController extends ModuleAdminController
{
    // À adapter selon ta configuration
    protected $engine_endpoints = [
        'bing' => 'https://api.indexnow.org/indexnow',
        // Ajoute d'autres moteurs si besoin
    ];

    protected $engines = [
        'bing' => 'Bing',
        // Ajoute d'autres moteurs si besoin
        'google' => 'Google',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function initContent()
    {
        parent::initContent();

        $type = Tools::getValue('type', 'product');
        $urls = $this->getUrlsByType($type);

        $types = [
            'product' => $this->l('Produits'),
            'category' => $this->l('Catégories'),
            'brand' => $this->l('Marques'),
            'page' => $this->l('Pages CMS'),
        ];
        $adminToken = Tools::getAdminTokenLite('AdminWebsourceindexnowBulkurls');

        $this->context->smarty->assign([
            'admin_token' => $adminToken,
            'types' => $types,
            'current_type' => $type,
            'urls' => $urls,
            'send_url_action' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminWebsourceindexnowBulkurls'),
        ]);

        $this->content = $this->createTemplate('bulkurls.tpl')->fetch();
        $this->context->smarty->assign(array(
            'content' => $this->content
        ));
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitBulkSendUrls')) {
            $urls = Tools::getValue('urls_to_send', []);
            if (empty($urls)) {
                $this->errors[] = "Aucune URL sélectionnée.";
                return;
            }

            // Envoi à IndexNow (en bulk)
            $resultsIndexNow = $this->sendIndexnowBulkUrls($urls);
            foreach ($resultsIndexNow as $msg) {
                if (strpos($msg, 'error') !== false || strpos($msg, 'Erreur') !== false) {
                    $this->errors[] = $msg;
                } else {
                    $this->confirmations[] = $msg;
                }
            }

            // Envoi à Google Indexing API (en batch, 100 max par requête)
            $accessToken = $this->getGoogleAccessToken(); // À adapter selon ton module
            if ($accessToken) {
                $chunks = array_chunk($urls, 100);
                foreach ($chunks as $batch) {
                    $result = $this->sendGoogleIndexingBatch($batch, $accessToken, 'URL_UPDATED');
                    if (!empty($result['error'])) {
                        $this->errors[] = "Erreur Google Indexing : " . $result['error'];
                    } else {
                        $this->confirmations[] = count($batch) . " URL(s) envoyée(s) à Google Indexing API.";
                    }
                }
            } else {
                $this->errors[] = "Impossible de récupérer le token Google Indexing API.";
            }
        }
    }

    /**
     * Récupère les URLs selon le type demandé.
     * Retourne un tableau de tableaux (id + url) pour affichage dans le template.
     */
    protected function getUrlsByType($type)
    {
        $urls = [];
        $link = $this->context->link;

        switch ($type) {
            case 'product':
                $products = Db::getInstance()->executeS('SELECT id_product FROM '._DB_PREFIX_.'product WHERE active=1');
                foreach ($products as $product) {
                    $urls[] = [
                        'id' => $product['id_product'],
                        'url' => $link->getProductLink($product['id_product']),
                    ];
                }
                break;

            case 'category':
                $categories = Db::getInstance()->executeS('SELECT id_category FROM '._DB_PREFIX_.'category WHERE active=1 AND id_category != 1');
                foreach ($categories as $category) {
                    $urls[] = [
                        'id' => $category['id_category'],
                        'url' => $link->getCategoryLink($category['id_category']),
                    ];
                }
                break;

            case 'brand':
                $brands = Db::getInstance()->executeS('SELECT id_manufacturer FROM '._DB_PREFIX_.'manufacturer');
                foreach ($brands as $brand) {
                    $urls[] = [
                        'id' => $brand['id_manufacturer'],
                        'url' => $link->getManufacturerLink($brand['id_manufacturer']),
                    ];
                }
                break;

            case 'page':
                $pages = Db::getInstance()->executeS('SELECT id_cms FROM '._DB_PREFIX_.'cms WHERE active=1');
                foreach ($pages as $page) {
                    $urls[] = [
                        'id' => $page['id_cms'],
                        'url' => $link->getCMSLink($page['id_cms']),
                    ];
                }
                break;
        }
        return $urls;
    }

    /**
     * Envoie un lot d'URLs à IndexNow (Bing, bulk).
     * @param array $urls Liste des URLs à envoyer.
     * @return array Résultats par moteur.
     */
    public function sendIndexnowBulkUrls(array $urls)
    {
        $key = Configuration::get('WEBSOURCEINDEXNOW_KEY');
        if (!$key) {
            return ["<div class='error'>Clé API manquante.</div>"];
        }

        // On ne traite que Bing ici, car c'est le seul endpoint officiel IndexNow
        $endpoint = 'https://api.indexnow.org/indexnow';

        $use_ssl = (bool)Configuration::get('PS_SSL_ENABLED');
        $domain = $use_ssl ? Configuration::get('PS_SHOP_DOMAIN_SSL') : Configuration::get('PS_SHOP_DOMAIN');
        $scheme = $use_ssl ? 'https' : 'http';
        $keyLocation = $scheme . '://' . $domain . '/' . $key . '.txt';

        // Vérification que $urls n'est pas vide
        if (empty($urls)) {
            return ["<div class='error'>Aucune URL à envoyer.</div>"];
        }

        // Vérification que toutes les URLs sont du même host
        $host = parse_url($urls[0], PHP_URL_HOST);
        foreach ($urls as $url) {
            if (parse_url($url, PHP_URL_HOST) !== $host) {
                return ["<div class='error'>Toutes les URLs doivent appartenir au même domaine pour IndexNow.</div>"];
            }
        }

        $data = [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $keyLocation,
            'urlList' => $urls,
        ];

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

        $results = [];
        $label = 'Bing (IndexNow)';
        if ($error) {
            $results[] = "<div class='error'>$label : Erreur CURL : " . htmlspecialchars($error) . "</div>";
        } elseif ($httpCode === 200) {
            $results[] = "<div class='success'>$label : Succès, URLs envoyées.</div>";
        } elseif ($httpCode === 202) {
            $results[] = "<div class='success'>$label : Succès partiel, URLs acceptées et en attente de traitement.</div>";
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

        return $results;
    }

    /**
     * Envoie un lot d'URLs à l'API Google Indexing via le endpoint /batch.
     * @param array $urls Liste des URLs à indexer (max 100 par appel)
     * @param string $accessToken Jeton OAuth2 valide avec scope https://www.googleapis.com/auth/indexing
     * @param string $type Type de notification (URL_UPDATED ou URL_DELETED)
     * @return array Résultat de la requête batch
     */
    public function sendGoogleIndexingBatch(array $urls, $accessToken, $type = 'URL_UPDATED')
    {
        if (count($urls) === 0) {
            return ['error' => 'Aucune URL à envoyer.'];
        }
        if (count($urls) > 100) {
            return ['error' => 'Le batch Google est limité à 100 URLs.'];
        }
        $boundary = '===============GoogleBatch' . md5(uniqid());
        $delimiter = "--$boundary";
        $close_delim = "--$boundary--";
        $body = '';

        foreach ($urls as $i => $url) {
            $subBody = json_encode([
                'url' => $url,
                'type' => $type
            ]);
            $body .= $delimiter . "\r\n";
            $body .= "Content-Type: application/http\r\n";
            $body .= "Content-Transfer-Encoding: binary\r\n";
            $body .= "Content-ID: <$i>\r\n\r\n";
            $body .= "POST /v3/urlNotifications:publish HTTP/1.1\r\n";
            $body .= "Content-Type: application/json\r\n";
            $body .= "accept: application/json\r\n";
            $body .= "content-length: " . strlen($subBody) . "\r\n\r\n";
            $body .= $subBody . "\r\n";
        }
        $body .= $close_delim . "\r\n";

        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: multipart/mixed; boundary=\"$boundary\"",
            "Content-Length: " . strlen($body)
        ];

        $ch = curl_init('https://indexing.googleapis.com/batch');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return ['error' => $error];
        }
        if ($httpCode != 200) {
            return ['error' => "HTTP $httpCode", 'response' => $response];
        }
        return ['success' => true, 'response' => $response];
    }

    /**
     * À personnaliser : retourne le jeton d'accès Google Indexing API.
     * @return string|null
     */
    protected function getGoogleAccessToken()
    {
        return $this->module->getGoogleAccessToken();
    }
}
