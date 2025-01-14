<?php
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

function returnMsg( $_type, $_msg ) {

    if( $_type == 'error' )
        log::add('ondilo','debug','ip: ' . network::getClientIp() . ' msg:' . $_msg );
        
    $msg[$_type] = $_msg;
    echo json_encode($msg);
	die();
}

if (strpos($_SERVER['REQUEST_URI'], 'autorize?') !== false) {
    log::add('ondilo','debug','redirect to autorize');
    $correctUrl = str_replace('autorize?', 'autorize&', $_SERVER['REQUEST_URI']);
    header("Location: $correctUrl");
    exit;
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

        ?>
        <script>
        // Vérifie si la fenêtre d'origine est accessible
        if (window.opener) {
            // Exemple : Envoie un message à la fenêtre d'origine
            window.opener.postMessage('authSuccess', '*');

            // Ferme l'onglet courant
            window.close();
        } else {
            alert('Impossible de retourner à la fenêtre initiale.');
        }
        </script>
        <?php
        // $redirect_uri = network::getNetworkAccess( network::getUserLocation() ) . '/index.php?v=d&p=plugin&id=ondilo';
        // header("Location: $redirect_uri");
        exit;
        
    } else {
        returnMsg( 'error', __('Le token d\'authentification ne correspond pas', __FILE__) );
    }
}

header('Content-type: application/json');

if (!jeedom::apiAccess(init('apikey'), 'ondilo')) {
    returnMsg( 'error', __('Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (Ondilo)', __FILE__) );
}

