# validate-echo-request-php

PHP functions to validate Amazon Echo requests.

License: http://www.apache.org/licenses/LICENSE-2.0

Usage:

    include('valid_request.php');
    $valid = validate_request( $guid, $userid );
    if ( ! $valid['success'] )  {
        error_log( 'Request failed: ' . $valid['message'] );
        die();
    }

    // ... the rest of your skill goes here


Note that this package keeps a local cache of a certificate, so the
directory needs to be writeable. 

TODO: Stash the cert somewhere better, so that we don't have to do this.

