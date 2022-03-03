<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

echo "<h3>User_VAL</h3>";
echo "<p>Use this form to forge a «User_VAL» request on Catalyse API.</p>";

$CATALYSE_ENV = $_GET['CATALYSE_ENV'] ?? '';
$SCIPER = $_GET['SCIPER'] ?? '';
$GO = $_GET['Go'] ?? '';

?>
    <form name="catalysefrm" action="/wsc.php" method="get">
        <input type="number" name="SCIPER" value="<?php echo $_GET['SCIPER'] ?? ''; ?>" min="100000" max="999999" placeholder="A sciper number" required>
        <select name="CATALYSE_ENV" required>
            <option value="">---</option>
            <option value="test"<?php echo (!empty($_GET['CATALYSE_ENV']) && $_GET['CATALYSE_ENV'] == 'test') ? ' selected' : '' ; ?>>TEST (recette projet)</option>
            <option value="qual"<?php echo (!empty($_GET['CATALYSE_ENV']) && $_GET['CATALYSE_ENV'] == 'qual') ? ' selected' : '' ; ?>>QUAL (recette maintenance)</option>
            <option value="prod"<?php echo (!empty($_GET['CATALYSE_ENV']) && $_GET['CATALYSE_ENV'] == 'prod') ? ' selected' : '' ; ?>>PROD</option>
        </select>
        <input type="submit" name="Go" value="Go">
    </form>
<?php
if (!empty($CATALYSE_ENV) && !empty($SCIPER) && is_numeric($SCIPER)) {
    echo "<br>";
    $msg = "<p>Testing for user";
    // Define the CATALYSE ENV
    $msg .= " ". $SCIPER . " (<a href='https://people.epfl.ch/" . $SCIPER . "'>" . $SCIPER . "</a>) on environment ";

    if ('test' === $CATALYSE_ENV) {
        $CATALYSE_API_URL = getenv('CATALYSE_API_URL_TEST');
        $CATALYSE_API_KEY = getenv('CATALYSE_API_KEY_TEST');
        $CATALYSE_LANDING_PAGE = getenv('CATALYSE_LANDING_PAGE_TEST');
        $OAUTH_REDIRECT = getenv('OAUTH_REDIRECT_TEST');
        $WEBSRV_URL = getenv('WEBSRV_URL_TEST');
        $msg .= " TEST (" . $CATALYSE_API_URL . ").";
    } else if ('qual' === $CATALYSE_ENV) {
        $CATALYSE_API_URL = getenv('CATALYSE_API_URL_QUAL');
        $CATALYSE_API_KEY = getenv('CATALYSE_API_KEY_QUAL');
        $CATALYSE_LANDING_PAGE = getenv('CATALYSE_LANDING_PAGE_QUAL');
        $OAUTH_REDIRECT = getenv('OAUTH_REDIRECT_QUAL');
        $WEBSRV_URL = getenv('WEBSRV_URL_QUAL');
        $msg .= " QUAL (" . $CATALYSE_API_URL . ").";
    } else { // fallback to prod
        $CATALYSE_API_URL = getenv('CATALYSE_API_URL_PROD');
        $CATALYSE_API_KEY = getenv('CATALYSE_API_KEY_PROD');
        $CATALYSE_LANDING_PAGE = getenv('CATALYSE_LANDING_PAGE_PROD');
        $OAUTH_REDIRECT = getenv('OAUTH_REDIRECT_PROD');
        $WEBSRV_URL = getenv('WEBSRV_URL_PROD');
        $msg .= " PROD (" . $CATALYSE_API_URL . ").";
    }
    echo $msg . '</p>';

    // Define the APP ENV (LOCAL (docker) of REMOTE (LAMP TKGI))
    if ('catalyse-dev.epfl.ch' === $_SERVER['SERVER_NAME'] && '8123' == $_SERVER['SERVER_PORT']) {
        // We are LOCAL
        $OAUTH_REDIRECT = getenv('OAUTH_REDIRECT_LOCAL') . $OAUTH_REDIRECT;
        $OAUTH_CLIENT_ID = getenv('OAUTH_CLIENT_ID_LOCAL');
        $OAUTH_CLIENT_SECRET = getenv('OAUTH_CLIENT_SECRET_LOCAL');
    } else {
        // We are REMOTE
        $OAUTH_REDIRECT = getenv('OAUTH_REDIRECT_REMOTE') . $OAUTH_REDIRECT;
        $OAUTH_CLIENT_ID = getenv('OAUTH_CLIENT_ID_REMOTE');
        $OAUTH_CLIENT_SECRET = getenv('OAUTH_CLIENT_SECRET_REMOTE');
    }

    try {
        // '<User_VALs><User_VAL><LOGIN_NAME>169419</LOGIN_NAME></User_VAL></User_VALs>'
        $payload = new SimpleXMLElement('<User_VALs><User_VAL><LOGIN_NAME>' . $SCIPER . '</LOGIN_NAME></User_VAL></User_VALs>');
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
            ],
            'timeout' => 10
        ]);

        echo "<pre>";
        echo "Request payload:\n";
        var_dump($payload);
        echo "\nRequest status <b>" . $res->getStatusCode() . "</b>\n";
        echo "\nResponse body:\n";
        var_dump((string) $res->getBody());
        echo "</pre>";

        // http://catalyse-dev.epfl.ch:8123/wsc.php?CATALYSE_ENV=qual&sciper=169419
        if (200 !== $res->getStatusCode()) {
            throw new ClientException("Error processing request on catalyse API");
        }

    } catch (Exception | ClientException $e) {
        echo "<b>Error</b><br>";
        if ($e instanceof Exception) {
            echo stristr($e->getMessage(), 'for https://catalyse', true);
        } else {
            echo "ClientException";
        }
    }
}
