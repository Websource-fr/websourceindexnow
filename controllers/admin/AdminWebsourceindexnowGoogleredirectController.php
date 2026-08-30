<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminWebsourceindexnowGoogleredirectController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function initContent()
    {
        parent::initContent();

        $clientId = Configuration::get('WEBSOURCEINDEXNOW_GOOGLE_CLIENT_ID');
        $clientSecret = Configuration::get('WEBSOURCEINDEXNOW_GOOGLE_CLIENT_SECRET');
        $redirectUri = $this->context->link->getAdminLink('AdminWebsourceindexnowGoogleredirect', false);

        // Si on n'a pas encore de code, on redirige vers Google pour l'autorisation
        if (!Tools::getValue('code')) {
            $authUrl = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'response_type' => 'code',
                    'scope' => 'https://www.googleapis.com/auth/indexing',
                    'access_type' => 'offline',
                    'prompt' => 'consent'
                ]);
            Tools::redirectAdmin($authUrl);
            exit;
        }

        // Si on a un code, on échange contre un refresh token et un access token
        $code = Tools::getValue('code');
        $tokenUrl = 'https://oauth2.googleapis.com/token';

        $postFields = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($status == 200 && isset($result['refresh_token'])) {
            Configuration::updateValue('WEBSOURCEINDEXNOW_GOOGLE_EXPIRES_IN', pSQL($result['expires_in']));
            Configuration::updateValue('WEBSOURCEINDEXNOW_GOOGLE_REFRESH_TOKEN', pSQL($result['refresh_token']));
            Configuration::updateValue('WEBSOURCEINDEXNOW_GOOGLE_ACCESS_TOKEN', pSQL($result['access_token']));
            $this->context->smarty->assign([
                'success_message' => 'Le refresh token et l\'access token ont bien été enregistrés.',
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'expires_in' => $result['expires_in'],
            ]);
        } elseif ($status == 200 && isset($result['access_token'])) {
            Configuration::updateValue('WEBSOURCEINDEXNOW_GOOGLE_ACCESS_TOKEN', pSQL($result['access_token']));
            $this->context->smarty->assign([
                'success_message' => 'Access token obtenu, mais pas de refresh token (peut-être avez-vous déjà autorisé cette application).',
                'access_token' => $result['access_token'],
                'refresh_token' => '',
                'expires_in' => $result['expires_in'],
            ]);
        } else {
            $this->context->smarty->assign([
                'error_message' => 'Erreur lors de l\'obtention du token : ' . (isset($result['error_description']) ? $result['error_description'] : 'Erreur inconnue'),
            ]);
        }

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true) . '&configure=websourceindexnow');
        exit;
    }
}
