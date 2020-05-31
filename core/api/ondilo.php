<?php
header('Content-type: application/json');
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

function returnMsg( $_type, $_msg ) {

    if( $_type == 'error' )
        log::add('ondilo','debug','ip: ' . network::getClientIp() . ' msg:' . $_msg );
        
    $msg[$_type] = $_msg;
    echo json_encode($msg);
	die();
}

if ( init('action') == 'autorize' ) {

    if( init('state') == config::byKey( 'state', 'ondilo' ) ) {

        $resultToken = json_decode( ondilo::getToken( init('code' ) ), true );
    
        $expires_in = time() + $resultToken['expires_in'];
        config::save('access_token' , $resultToken['access_token'], 'ondilo'); 
        config::save('refresh_token', $resultToken['refresh_token'], 'ondilo'); 
        config::save('expires_in'   , $expires_in, 'ondilo'); 
    
        config::remove( 'network', 'ondilo' );
        config::remove( 'state', 'ondilo' );

        event::add('ondilo::token', array(
            'action' => 'configuration',
            'message' => '',
        ));   

        exit;
        
    } else {
        returnMsg( 'error', __('Le token d\'authentification ne correspond pas', __FILE__) );
    }
}

if (!jeedom::apiAccess(init('apikey'), 'ondilo')) {
    returnMsg( 'error', __('Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (Ondilo)', __FILE__) );
}

