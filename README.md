# validate-echo-request-php

PHP functions to validate Amazon Echo requests. This enforces all of the
requirements at
https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#verifying-the-signature-certificate-url

License: http://www.apache.org/licenses/LICENSE-2.0

Usage:

    $guid = '5c33db4b-1234-4567-7889-a8f849de6b68';
    $userid = 'AFPPR46KLASDJLKJDW788CASDFFHF6J6ERFIWCEI7GP4YDXFRBEJI';

    include('valid_request.php');
    $valid = validate_request( $guid, $userid );
    if ( ! $valid['success'] )  {
        error_log( 'Request failed: ' . $valid['message'] );
        die();
    }

    // ... the rest of your skill goes here


Note that this package keeps a local cache of a certificate, so you **MUST**
create the directory /var/cache/amazon_echo/ and make it writeable by
your web server user ID. If you want to stash your certs somewhere else,
you'll need to modify the $ECHO_CERT_CACHE variable in
validate_request.php

