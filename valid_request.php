<?php

/*

This came from http://pastebin.com/d0W9nX60 which in turn came from
https://forums.developer.amazon.com/forums/thread.jspa?threadID=8537

However, it has been hacked on quite a bit since then. Need to turn this
into a general purpose thing, without the hard-coded stuff.

Usage:

    include('valid_request.php');
    $valid = validate_request( $guid, $userid );
    if ( $valid['success'] )  {
        // Do stuff
    } else {
        error_log( 'Request failed: ' . $valid['message'] );
        die();
    }

*/

/*

    validate_request( $guid, $userid )

    Returns an array that looks like:

    ( success => 1 or 0,
      message => 'failure message' );
*/
function validate_request( $guid, $userid ) {
    # $valid = array( success => 0,
    #                message => '' );

// Make this dir, and chown it to your web server user.
// Requires trailing slash
$ECHO_CERT_CACHE = '/var/cache/amazon_echo/';

    // Capture Amazon's POST JSON request:
    $jsonRequest    = file_get_contents('php://input');
    $data           = json_decode($jsonRequest, true);

    // Validate that it even came from Amazon ...
    if ( !isset( $_SERVER['HTTP_SIGNATURECERTCHAINURL'] ) ) {
        return array( 'success' => 0,
                      'message' => "Looks like this didn't even come from Amazon" );
    }

    // Validate proper format of Amazon provided certificate chain url
    $valid_uri = valid_key_chain_uri( $_SERVER['HTTP_SIGNATURECERTCHAINURL'] );
    if ( $valid_uri != 1 ) {
        return array( 'success' => 0,
                      'message' => $valid_uri );
    }

    // Validate that account IDs match
    $valid_id = valid_ids( $guid, $userid, $data );
    if ( $valid_id != 1 ) {
        return array ( 'success' => 0,
                       'message' => $valid_id );
    }

    // Validate certificate signature
    $valid_cert = valid_cert( $jsonRequest, $data, $ECHO_CERT_CACHE );
    if ( $valid_cert != 1 ) {
        return array ( 'success' => 0,
                       'message' => $valid_cert );
    }

    // Validate time stamp
    $valid_time = valid_time( $data );
    if ( $valid_time != 1 ) {
        return array ('success' => 0,
                      'message' => $valid_time );
    }

    return array( 'success' => 1, 'message' => '' );
}



/*
 Validate keychainUri is proper (from Amazon)

 Follow requirements found at:
 https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#verifying-the-signature-certificate-url

*/
function valid_key_chain_uri( $keychainUri ){

    $uriParts = parse_url($keychainUri);

    if (strcasecmp($uriParts['host'], 's3.amazonaws.com') != 0)
        return ('The host for the Certificate provided in the header is invalid');

    if (strpos($uriParts['path'], '/echo.api/') !== 0)
        return ('The URL path for the Certificate provided in the header is invalid');

    if (strcasecmp($uriParts['scheme'], 'https') != 0)
        return ('The URL is using an unsupported scheme. Should be https');

    if (array_key_exists('port', $uriParts) && $uriParts['port'] != '443')
        return ('The URL is using an unsupported https port');

    return 1;
}

/*

    Verify that the various account IDs match what they should.

*/
function valid_ids( $guid, $userid, $data ) {

    $applicationIdValidation    = 'amzn1.ask.skill.' . $guid;
    $userIdValidation1          = 'amzn1.echo-sdk-account.' . $userid;
    $userIdValidation2          = 'amzn1.ask.account.' . $userid;

    //
    // Parse out key variables
    //
    $applicationId      = @$data['session']['application']['applicationId'];
    $userId             = @$data['session']['user']['userId'];

    // Die if applicationId isn't valid
    if ($applicationId != $applicationIdValidation) {
        return('Invalid Application id: ' . $applicationId);
    }

    // Die if this request isn't coming from the correct Amazon Account
    if (     ($userId != $userIdValidation1)
         and ($userId != $userIdValidation2) ) {
        return('Invalid User id: ' . $userId . '  Expected ' .
        $userIdValidation1 . ' or possibly ' . $userIdValidation2 );
    }

    return 1;

}

/*
    Validate that the certificate and signature are valid
*/
function valid_cert( $jsonRequest, $data, $ECHO_CERT_CACHE ) {
    // Determine if we need to download a new Signature Certificate Chain from Amazon
    $md5pem = $ECHO_CERT_CACHE .
              md5($_SERVER['HTTP_SIGNATURECERTCHAINURL']) .
              '.pem';
    $echoServiceDomain = 'echo-api.amazon.com';

    // If we haven't received a certificate with this URL before,
    // store it as a cached copy
    if (!file_exists($md5pem)) {
        file_put_contents($md5pem,
          file_get_contents($_SERVER['HTTP_SIGNATURECERTCHAINURL']));
    }

    // Validate certificate chain and signature
    $pem = file_get_contents($md5pem);
    $ssl_check = openssl_verify( $jsonRequest,
                                base64_decode($_SERVER['HTTP_SIGNATURE']),
                                $pem, 'sha1' );
    if ($ssl_check != 1) {
        return(openssl_error_string());
    }

    // Parse certificate for validations below
    $parsedCertificate = openssl_x509_parse($pem);
    if (!$parsedCertificate) {
        return('x509 parsing failed');
    }

    // Check that the domain echo-api.amazon.com is present in
    // the Subject Alternative Names (SANs) section of the signing certificate
    if(strpos( $parsedCertificate['extensions']['subjectAltName'],
           $echoServiceDomain) === false) {
        return('subjectAltName Check Failed');
    }

    // Check that the signing certificate has not expired
    // (examine both the Not Before and Not After dates)
    $validFrom = $parsedCertificate['validFrom_time_t'];
    $validTo   = $parsedCertificate['validTo_time_t'];
    $time      = time();
    if (!($validFrom <= $time && $time <= $validTo)) {
        return('certificate expiration check failed');
    }

    return 1;
}

/*
    validate time stamp on request
*/
function valid_time( $data ) {

    // Check the timestamp of the request and ensure it was within the past minute
    $requestTimestamp   = @$data['request']['timestamp'];
    if (time() - strtotime($requestTimestamp) > 60) {
        return('timestamp validation failure.. Current time: ' . time()
          . ' vs. Timestamp: ' . $requestTimestamp);
    }

    return 1;
}

/*

*/
function sendresponse( $response, $me ) {

    $response = array (
       "version" => $me['version'],
        'response' => array (
            'outputSpeech' => array (
                'type' => 'PlainText',
                'text' => $response
            ),

             'card' => array (
                   'type' => 'Simple',
                   'title' => $me['name'],
                   'content' => $response
             ),

            'shouldEndSession' => 'true'
        ),
    );

    echo json_encode($response);
}


?>
