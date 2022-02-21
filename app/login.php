<?php
// https://oauth2-client.thephpleague.com/usage/
require __DIR__ . '/vendor/autoload.php';

$log = new Monolog\Logger('catalyse-access');
$log->pushHandler(new Monolog\Handler\StreamHandler('app.log', Monolog\Logger::DEBUG));
// Log format are "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"

$provider = new \League\OAuth2\Client\Provider\GenericProvider([
    'clientId'                => getenv()['OAUTH_CLIENT_ID'],     // The client ID assigned to you by the provider
    'clientSecret'            => getenv()['OAUTH_CLIENT_SECRET'], // The client password assigned to you by the provider
    'redirectUri'             => getenv()['OAUTH_REDIRECT'],
    'urlAuthorize'            => 'https://tequila.epfl.ch/v2/OAUTH2IdP/auth',
    'urlAccessToken'          => 'https://tequila.epfl.ch/v2/OAUTH2IdP/token',
    'urlResourceOwnerDetails' => 'https://tequila.epfl.ch/v2/OAUTH2IdP/userinfo'
]);

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
    header('Location: ' . $authorizationUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {

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

        try {

            // https://docs.guzzlephp.org/en/stable/quickstart.html
            $websrv = new GuzzleHttp\Client(['base_uri' => 'https://websrv.epfl.ch/cgi-bin/']);
            $res = $websrv->request('GET', 'rwsaccred/getRights', [
                'query' => [
                    'app' => getenv()['WEBSRV_APP_NAME'],
                    'caller' => getenv()['WEBSRV_APP_CALLER'],
                    'password' => getenv()['WEBSRV_APP_PASSWORD'],
                    'rightid' => 'sig0000',
                    'persid' => $loggedSciper,
                ]
            ]);

            if (200 !== $res->getStatusCode()) {
                throw new Exception("Error processing request on websrv");
            }

            $result = json_decode($res->getBody())->result;

            if (sizeof($result)) {
                $log->info("$loggedSciper do have sig0000", $result);

                try {
                    // '<User_VALs><User_VAL><LOGIN_NAME>169419</LOGIN_NAME></User_VAL></User_VALs>'
                    $payload = new SimpleXMLElement('<User_VALs><User_VAL><LOGIN_NAME>' . $loggedSciper . '</LOGIN_NAME></User_VAL></User_VALs>');
                    // https://docs.guzzlephp.org/en/stable/quickstart.html
                    $catalyseAPI = new GuzzleHttp\Client(['base_uri' => getenv()['CATALYSE_API_URL']]);
                    $res = $catalyseAPI->request('POST', 'User_VAL', [
                        'query'   => [
                            'apikey'      => getenv()['CATALYSE_API_KEY']
                        ],
                        'body'    => $payload->asXML(),
                        // curl -H "Content-Type: application/xml" -H "Accept: application/xml" -X POST -d '<User_VALs><User_VAL><LOGIN_NAME></LOGIN_NAME></User_VAL></User_VALs>' https://catalyse-test-proj.epfl.ch/page.aspx/en/eai/api/User_VAL\?apikey\=XXX
                        'headers' => [
                            'Accept'       => 'application/xml',
                            'Content-Type' => 'application/xml',
                        ]
                    ]);

                    if (200 !== $res->getStatusCode()) {
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

            // in all case redirect to https://catalyse-buyer.epfl.ch
            header( 'Location: https://catalyse-buyer.epfl.ch' );

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
