<?php

/*
 * Use this script to make a text-to-speech call to a phone number using Voice API
 *
 * To use this script:
 * 1. Copy this script to a local directory <NEXMO_DIR>.
 * 2. In <NEXMO_DIR>: use composer to install the lcobucci/jwt library used to generate a jwt.
 * 		composer require lcobucci/jwt
 * 3. Set the parameters between lines 23 and 34.
 * 4. Run the script:
 * 		php voice_api_first_tts_call.php
 */

require __DIR__ . '/vendor/autoload.php';

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;

/*
 *  Set the parameters to run this script
 */
 $nexmo_key = "API_KEY";
 $nexmo_secret = "API_SECRET";

//Leave blank unless you have already created an application
$application_id = "";
//If you add an application ID here, add the private key in a file with the
//same name as the application ID in  the same directory as this script.

//Change this to your phone number
$phone_number_to_call = "";

//And the phone number you are calling from
//This does not have to be a real phone number, just in the correct format
$virtual_number = "441632960961";

/*
 * The base URL for Nexmo endpoints.
 */
$base_url = 'https://api.nexmo.com' ;
$version = '/v1';
$action = '';

/*
 Function to generate a JWT using the private key associated with an application.
 */
function generate_jwt( $application_id, $keyfile) {

	$jwt = false;
	date_default_timezone_set('UTC');    //Set the time for UTC + 0
	$key = file_get_contents($keyfile);  //Retrieve your private key
	$signer = new Sha256();
	$privateKey = new Key($key);

	$jwt = (new Builder())->setIssuedAt(time() - date('Z')) // Time token was generated in UTC+0
	->set('application_id', $application_id) // ID for the application you are working with
	->setId( base64_encode( mt_rand (  )), true)
	->sign($signer,  $privateKey) // Create a signature using your private key
	->getToken(); // Retrieves the JWT

	return $jwt;
}

echo ("Hi, using your key: " . $nexmo_key . " and secret: " . $nexmo_secret . "to make a Call with Voice API.\n\n");

/*
  * If you have not already created an application, this requests creates one for you and stores
  * the private key locally in a local file $application_id.
 */
if (empty($application_id))
{
	echo ("Can't see an application, let's create one for you.\n\n");
	$action = '/applications/?';

	$url = $base_url . $version . $action . http_build_query([
			'api_key' =>  $nexmo_key,
			'api_secret' => $nexmo_secret,
			'name' => 'First Voice API Call',
			'type' => 'voice',
			'answer_url' => 'https://example.com/ncco',
			'event_url' => 'https://example.com/call_status'
	]);

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json", "Content-Length: 0" ));
	curl_setopt($ch, CURLOPT_HEADER, 1);
	$response = curl_exec($ch);

	$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$header = substr($response, 0, $header_size);
	$body = substr($response, $header_size);

	if (strpos($header, '201')){
		$application = json_decode($body, true);
		if (! isset ($application['type'])){
			echo("Application \"" . $application['name']
					. "\" has an ID of:" . $application['id'] . "\n\n" ) ;
			$application_id =  $application['id'];
		}
		echo ("Saving your private key to a local file.\n\n");
		$private_key = preg_replace( "/RSA PRIVATE KEY/", "PRIVATE KEY", $application['keys']['private_key']);
		file_put_contents($application_id, $private_key );
	}
}
else{
	if ( ! is_readable($application_id) ){
		print ("Please add the private key for your application in a file named " . $application_id + " in  the same directory as this script.\n\n" );

	}

}

echo ("Using application ID " . $application_id . " to call " .  $phone_number_to_call . "\n\n");

/*
 Make a TTS Call to a phone number
 */
echo ("Generate a JWT for  " . $application_id . ".\n\n");
$jwt = generate_jwt($application_id, $application_id);

echo ("This JWT authenticates you when you make a request to one of our endpoints: \n\n  " . $jwt . ".\n\n");

$action = '/calls';
//Add the JWT to the request headers
$headers =  array('Content-Type: application/json', "Authorization: Bearer " . $jwt ) ;

$payload = "{
    \"to\":[{
        \"type\": \"phone\",
        \"number\": \"$phone_number_to_call\"
    }],
    \"from\": {
        \"type\": \"phone\",
        \"number\": \"$virtual_number\"
    },
    \"answer_url\": [\"https://nexmo-community.github.io/ncco-examples/first_call_talk.json\"]
}";
echo ("Use the following payload to make the Call: \n\n" .  $payload . "\n\n");

echo ("answer_url is pointing to the webhook endpoint providing the NCCO that manages the Call.\n\n" );

echo ("And make the Call. \n\n" );
//Create the request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $base_url . $version . $action);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$response = curl_exec($ch);

echo ("The Call status is: " . $response . "\n\n" );
