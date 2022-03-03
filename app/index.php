<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

# Monolog is a logger
# Logs format are "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
$log = new Monolog\Logger('catalyse-access');
$log->pushHandler(new Monolog\Handler\StreamHandler('app.log', Monolog\Logger::DEBUG));

// redirect to catalyse.epfl.ch if old URL
if ('catalyse-access.epfl.ch' === $_SERVER['SERVER_NAME']) {
    $redirectURL = $_SERVER['REQUEST_SCHEME'] . '://catalyse.epfl.ch';
    // $redirectURL .= $_SERVER['SERVER_PORT'] ? ':'.$_SERVER['SERVER_PORT'] : '';
    header('Location: ' . $redirectURL);
    die();
}

// Define the CATALYSE ENV
$CATALYSE_ENV = $_GET['CATALYSE_ENV'] ?? "prod";
$log->debug("CATALYSE_ENV is", [$CATALYSE_ENV]);
if ('test' === $CATALYSE_ENV) {
    $CATALYSE_API_URL = getenv('CATALYSE_API_URL_TEST');
    $CATALYSE_API_KEY = getenv('CATALYSE_API_KEY_TEST');
    $CATALYSE_LANDING_PAGE = getenv('CATALYSE_LANDING_PAGE_TEST');
    $OAUTH_REDIRECT = getenv('OAUTH_REDIRECT_TEST');
    $WEBSRV_URL = getenv('WEBSRV_URL_TEST');
} else if ('qual' === $CATALYSE_ENV) {
    $CATALYSE_API_URL = getenv('CATALYSE_API_URL_QUAL');
    $CATALYSE_API_KEY = getenv('CATALYSE_API_KEY_QUAL');
    $CATALYSE_LANDING_PAGE = getenv('CATALYSE_LANDING_PAGE_QUAL');
    $OAUTH_REDIRECT = getenv('OAUTH_REDIRECT_QUAL');
    $WEBSRV_URL = getenv('WEBSRV_URL_QUAL');
} else { // fallback to prod
    $CATALYSE_API_URL = getenv('CATALYSE_API_URL_PROD');
    $CATALYSE_API_KEY = getenv('CATALYSE_API_KEY_PROD');
    $CATALYSE_LANDING_PAGE = getenv('CATALYSE_LANDING_PAGE_PROD');
    $OAUTH_REDIRECT = getenv('OAUTH_REDIRECT_PROD');
    $WEBSRV_URL = getenv('WEBSRV_URL_PROD');
}


// Define the APP ENV (LOCAL (docker) of REMOTE (LAMP TKGI))
if ('catalyse-dev.epfl.ch' === $_SERVER['SERVER_NAME'] && '8123' == $_SERVER['SERVER_PORT']) {
    // We are LOCAL
    $OAUTH_REDIRECT = getenv('OAUTH_REDIRECT_LOCAL').$OAUTH_REDIRECT;
    $OAUTH_CLIENT_ID = getenv('OAUTH_CLIENT_ID_LOCAL');
    $OAUTH_CLIENT_SECRET = getenv('OAUTH_CLIENT_SECRET_LOCAL');
} else {
    // We are REMOTE
    $OAUTH_REDIRECT = getenv('OAUTH_REDIRECT_REMOTE').$OAUTH_REDIRECT;
    $OAUTH_CLIENT_ID = getenv('OAUTH_CLIENT_ID_REMOTE');
    $OAUTH_CLIENT_SECRET = getenv('OAUTH_CLIENT_SECRET_REMOTE');
}
 

$provider = new \League\OAuth2\Client\Provider\GenericProvider([
    'clientId'                => $OAUTH_CLIENT_ID,     // The client ID assigned to you by the provider
    'clientSecret'            => $OAUTH_CLIENT_SECRET, // The client password assigned to you by the provider
    'redirectUri'             => $OAUTH_REDIRECT,      // Wherever Tequila will redirect the user. Have to match the oAuth client configuration
    // TODO: ask if our oAuth key works on tequila-test...
    'urlAuthorize'            => 'https://tequila.epfl.ch/v2/OAUTH2IdP/auth',
    'urlAccessToken'          => 'https://tequila.epfl.ch/v2/OAUTH2IdP/token',
    'urlResourceOwnerDetails' => 'https://tequila.epfl.ch/v2/OAUTH2IdP/userinfo'
]);

// https://oauth2-client.thephpleague.com/usage/
// If we don't have an authorization code then get one
if (!isset($_GET['code'])) {

    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    // (e.g. state).
    $authorizationUrl = $provider->getAuthorizationUrl(
        ['scope' =>
         ['Tequila.profile',
          # TODO: guess the ones for `Statut` and `droit-sig0000`
         ]]);

    // Get the state generated for you and store it to the session.
    $_SESSION['oauth2state'] = $provider->getState();

    // Redirect the user to the authorization URL.
    $log->debug("Redirect the user to the authorization URL", [$authorizationUrl]);
    header('Location: ' . $authorizationUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {

    $log->debug("Mitigate CSRF attack, cleansing previous stored state");

    if (isset($_SESSION['oauth2state'])) {
        unset($_SESSION['oauth2state']);
    }

    exit('Invalid state');

} else {

    try {

        // Try to get an access token using the authorization code grant.
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);


        // We have an access token, which we may use in authenticated
        // requests against the service provider's API.
        //echo 'Access Token: ' . $accessToken->getToken() . "<br>";
        //echo 'Refresh Token: ' . $accessToken->getRefreshToken() . "<br>";
        //echo 'Scopes: ' . $provider->getDefaultScopes() . "<br>";
        // Not Working
        // echo 'Expired in: ' . $accessToken->getExpires() . "<br>";
        // echo 'Already expired? ' . ($accessToken->hasExpired() ? 'expired' : 'not expired') . "<br>";

        // Using the access token, we may look up details about the
        // resource owner.
        $resourceOwner = $provider->getResourceOwner($accessToken);

        //var_dump($resourceOwner->toArray()['Sciper']);
        $loggedSciper = $resourceOwner->toArray()['Sciper'];

        $log->debug("Login successful", $resourceOwner->toArray());

        try {

            // https://docs.guzzlephp.org/en/stable/quickstart.html
            $websrv = new GuzzleHttp\Client(['base_uri' => $WEBSRV_URL]);
            // 
            // // User is employee?
            // $employeeres = $websrv->request('GET', 'rwspersons/getPerson', [
            //     'query' => [
            //         'app' => getenv('WEBSRV_APP_NAME'),
            //         'caller' => getenv('WEBSRV_APP_CALLER'),
            //         'password' => getenv('WEBSRV_APP_PASSWORD'),
            //         'id' => $loggedSciper,
            //     ]
            // ]);
            // 
            // if (200 !== $employeeres->getStatusCode()) {
            //     $log->debug('websrv request returned ' . $employeeres->getStatusCode(), [$employeeres->getBody()]);
            //     throw new Exception("Error processing request on websrv (getPerson)");
            // }
            // $employee = json_decode($employeeres->getBody())->result;

            // User has sig000 rights?
            $sig0000res = $websrv->request('GET', 'rwsaccred/getRights', [
                'query' => [
                    'app' => getenv('WEBSRV_APP_NAME'),
                    'caller' => getenv('WEBSRV_APP_CALLER'),
                    'password' => getenv('WEBSRV_APP_PASSWORD'),
                    'rightid' => 'sig0000',
                    'persid' => $loggedSciper,
                ]
            ]);

            if (200 !== $sig0000res->getStatusCode()) {
                $log->debug('websrv request returned ' . $sig0000res->getStatusCode(), [$sig0000res->getBody()]);
                throw new Exception("Error processing request on websrv (getRights)");
            }
            $sig0000 = json_decode($sig0000res->getBody())->result;

            // The user is employee with a least one right sig0000, process
            // if ('Personnel' === $employee->status && sizeof($sig0000)) {
            if (sizeof($sig0000)) {
                $log->info("$loggedSciper do have sig0000", $sig0000);

                try {
                    // '<User_VALs><User_VAL><LOGIN_NAME>169419</LOGIN_NAME></User_VAL></User_VALs>'
                    $payload = new SimpleXMLElement('<User_VALs><User_VAL><LOGIN_NAME>' . $loggedSciper . '</LOGIN_NAME></User_VAL></User_VALs>');
                    // https://docs.guzzlephp.org/en/stable/quickstart.html
                    $catalyseAPI = new GuzzleHttp\Client(['base_uri' => $CATALYSE_API_URL]);
                    $res = $catalyseAPI->request('POST', 'User_VAL', [
                        'query'   => [
                            'apikey'      => $CATALYSE_API_KEY
                        ],
                        'body'    => $payload->asXML(),
                        // curl -H "Content-Type: application/xml" -H "Accept: application/xml" -X POST -d '<User_VALs><User_VAL><LOGIN_NAME></LOGIN_NAME></User_VAL></User_VALs>' https://catalyse-test-proj.epfl.ch/page.aspx/en/eai/api/User_VAL\?apikey\=XXX
                        'headers' => [
                            'Accept'       => 'application/xml',
                            'Content-Type' => 'application/xml',
                        ]
                    ]);

                    if (200 !== $res->getStatusCode()) {
                        $log->debug('CatalyseAPI request returned ' . $res->getStatusCode(), [$res->getBody()]);
                        throw new ClientException("Error processing request on catalyse API");
                    }

                } catch (Exception | ClientException $e) {

                    if ($e instanceof Exception) {
                        $log->error($e->getMessage());
                    } else {
                        $log->error(Psr7\Message::toString($e->getRequest()));
                        $log->error(Psr7\Message::toString($e->getResponse()));
                    }

                }

            } else {
                $log->info("$loggedSciper doesn't have sig0000");
            }

            // sleep(1);
            // in all case redirect to https://catalyse-buyer.epfl.ch
            // this send a 302
            header( 'Location: ' . $CATALYSE_LANDING_PAGE );
            // job's done
            exit();

        } catch (Exception | ClientException $e) {

            if ($e instanceof Exception) {
                $log->error($e->getMessage());
            } else {
                $log->error(Psr7\Message::toString($e->getRequest()));
                $log->error(Psr7\Message::toString($e->getResponse()));
            }

        }

    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

        // Failed to get the access token or user details.
        exit($e->getMessage());

    }

}
