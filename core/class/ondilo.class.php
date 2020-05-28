<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';
include_file('core', 'ondilo.api', 'class', 'ondilo');

class ondilo extends eqLogic {
    /*     * *************************Attributs****************************** */
    private $plugin = 'ondilo';
    private $type   = array(
        'outdoor_inground_pool',
        'outdoor_aboveground_pool',
        'indoor_inground_pool',
        'indoor_aboveground_pool',
        'outdoor_spa',
        'indoor_spa',
    );


    /*     * ***********************Methode static*************************** */
    public static function cron5() {
        self::refreshTokens();
    }

    public static function cron15() {

        self::pull();
    }

    public static function getAuthorizationCode() {

        $network      = network::getUserLocation();
        $redirect_uri = network::getNetworkAccess( $network ) . '/plugins/ondilo/core/api/ondilo.php?action=autorize';
        $state        = config::genKey(17);

        config::save('state', $state, 'ondilo'); 
        config::save('network', $network, 'ondilo'); 

        $ondilo = new ondiloAPI();
        return $ondilo->getAuthorizationCode( $redirect_uri, $state );
    }

    public static function getToken( $_code ) {

        $network      = config::byKey( 'network', 'ondilo' );
        $redirect_uri = network::getNetworkAccess( $network ) . '/plugins/ondilo/core/api/ondilo.php?action=autorize';

        $ondilo = new ondiloAPI();
        return $ondilo->getToken( $_code, $redirect_uri );
    }

    public static function refreshTokens() {

        if( ( $result = config::byKey( 'expires_in', 'ondilo', 0 ) - time() ) <= 600 ) {

            $ondilo = new ondiloAPI();
            $resultToken = $ondilo->refreshTokens( config::byKey( 'refresh_token', 'ondilo' ) );
            if( is_json( $resultToken ) ) {
                $tokens = json_decode( $resultToken, true );
                $expires_in = time() + $tokens['expires_in'];
                config::save('access_token', $tokens['access_token'], 'ondilo'); 
                config::save('expires_in'  , $expires_in, 'ondilo'); 
            }
        } else {

            if( $result < 0) {
                config::remove('expires_in', 'ondilo');
            }

        }
        
        return;
    }

    public static function pull() {

        $plugin   = plugin::byId('ondilo');
        $eqLogics = eqLogic::byType($plugin->getId());

        if( count( $eqLogics ) > 0 ) {

            foreach( $eqLogics as $eqLogic ) {

                if( is_object( $eqLogic ) ) {

                    $eqLogic->lastMeasures();
                    $eqLogic->recommendations();
                }
            }
        }
    }

    public static function discoverPools() {

        $ondilo = new ondiloAPI();
        $ondilo->setAccessToken( config::byKey( 'access_token', 'ondilo' ) );
        $poolsResult  = $ondilo->getPools(); 

        log::add('ondilo','debug','pool: ' . print_r($poolsResult, true) );
        
        if( is_json( $poolsResult ) ) {
            $pools = json_decode( $poolsResult, true );

            foreach ( $pools as $pool) {

                $logicalId = 'ondilo-' . $pool['id'];
                $eqLogic = ondilo::byLogicalId( $logicalId , 'ondilo');
                
				if ( ! is_object( $eqLogic ) ) {

                    log::add( 'ondilo', 'debug', 'Ondilo ' . $pool['name'] . ':'. $poolsResult );
                    
					$eqLogic = new ondilo();
					$eqLogic->setName( $pool['name'] );
					$eqLogic->setIsEnable(1);
					$eqLogic->setIsVisible(1);
					$eqLogic->setLogicalId( $logicalId );
                    $eqLogic->setEqType_name( 'ondilo' );
                    $eqLogic->setConfiguration( 'id'              , $pool['id'] );
                    $eqLogic->setConfiguration( 'type'            , $pool['type'] );
                    $eqLogic->setConfiguration( 'volume'          , $pool['volume'] );
                    $eqLogic->setConfiguration( 'typeDisinfection', $pool['disinfection']['primary'] );
                    $eqLogic->setConfiguration( 'uvSanitizer'     , $pool['disinfection']['secondary']['uv_sanitizer'] );
                    $eqLogic->setConfiguration( 'ozonator'        , $pool['disinfection']['secondary']['ozonator'] );
                
                    $deviceInfos = $ondilo->getDevice( $pool['id'] );
                    
                    if( is_json( $deviceInfos ) ) {
                        $deviceInfos = json_decode( $deviceInfos, true );
                        foreach ( $deviceInfos as $info => $value ) {
                            $eqLogic->setConfiguration( $info, $value );
                        }
                    }

                    $configuration = $ondilo->getConfiguration( $pool['id'] );
    
                    if( is_json( $configuration ) ) {
                        $configuration = json_decode( $configuration, true );
                        foreach ( $configuration as $conf => $value ) {
                            $eqLogic->setConfiguration( $conf, $value );
                        }
                    }

                    $eqLogic->save();
                }
                
                try {
                
                    $eqLogic->createCmd( 'common' );

                    if( $pool['disinfection']['primary'] == 'salt' ) {

                        $eqLogic->createCmd( 'salt' );

                    } else {
                        
                        $eqLogic->createCmd( 'tds' );
                        $eqLogic->createCmd( 'salt' );
                    }

                } catch (\Throwable $th) {
                    log::add( 'ondilo', 'debug', 'Throwable ' . $th );
                }

                $eqLogic->lastMeasures();

                $image = $eqLogic->getImage();

                $eqLogic->setConfiguration( 'image', $image );
                $eqLogic->save();

                event::add('jeedom::alert', array(
                    'level' => 'success',
                    'page' => 'ondilo',
                    'message' => __('Ico inclus avec succès : ' . $pool['name'] , __FILE__),
                ));
            }
            return true;
        }
    }

    /*     * *********************Méthodes d'instance************************* */

    private function createCmd( $_type, $_replace = null ) {

        log::add('ondilo','debug','Lancement de la création de commande pour le type : ' . $_type );

        $file = dirname(__FILE__) . '/../config/templates/' . $_type . '.json';

        $templateCmd = is_json( file_get_contents( $file ), array() );

        if ( is_array( $templateCmd ) ) {

            if( isset( $templateCmd['commands'] ) ) {

                foreach ( $templateCmd['commands'] as $command ) {

                    if( ! is_null( $_replace ) ) {

                        foreach( $command['configuration'] as $key => $value ) {

                            if( empty( $_replace[$value] ) ) {
                                continue;
                            }

                            $command['configuration'][$key] = $_replace[$value];
                        }
                    }

                    $cmd = $this->getCmd( null, $command['logicalId'] );

                    if ($cmd == null || !is_object( $cmd )) {

                        log::add('ondilo','debug','Création commande:'.$command['logicalId']);

                        $cmd = new ondiloCmd();
                        $cmd->setEqLogic_id( $this->getId() );
                
                        try {

                            utils::a2o( $cmd, $command);
                            $cmd->save();

                        } catch (Exception $e) {
            
                            log::add('ondilo','error','e : ' . print_r($e, true) );
                        }
                    }
                }

                return true;
            }
        } 
                
        return false;
    }

    private function recommendations() {

        $id = $this->getConfiguration( 'id', '' );
        $ondilo = new ondiloAPI();
        $ondilo->setAccessToken( config::byKey( 'access_token', 'ondilo' ) );

        $getRecommendations  = is_json( $ondilo->getRecommendations( $id ), array() );
        log::add('ondilo','debug','getRecommendations: '. print_r($getRecommendations, true));    
        
        foreach ( $getRecommendations as $recommendation ) {

            message::add(
                'Ondilo - ICO: ' . $recommendation['title'],
                $recommendation['message'],
                '',
                $this->getLogicalId()
            );
        }

    }

	public function getImage($_which = 'grid', $_ver = '@2x') {
 
        $type    = $this->getConfiguration('type',false);

        $default = 'plugins/ondilo/plugin_info/ondilo_icon.png';

        if( in_array( $type, $this->type ) ) {

            if( strpos( $type, 'pool' ) ) {
                $img = 'pool';
            } else {
                $img = 'spa';
            }

            $file = sprintf( 'plugins/ondilo/desktop/images/%s.png', $img );
            return $file;

            if( file_exists( $file ) ) {
                log::add('ondilo','debug','file_exists file: '. print_r($file, true));
                return $file;
            }
        }

        return $default;
    }

    public function toHtml($_version = 'dashboard') {

        if( (bool)config::byKey( 'custom_widget', 'ondilo', 0 ) ) {

            $replace = $this->preToHtml($_version);
            if (!is_array($replace)) {
                return $replace;
            }
            $version = jeedom::versionAlias($_version);
            if ($this->getDisplay('hideOn' . $version) == 1) {
                return '';
            }
            /* ------------ Ajouter votre code ici ------------*/
            $replace['#icoImage#']    = $this->getImage();
            $replace['#type#']        = $this->getConfiguration('type',false);
    
            $cmd = $this->getCmd(null, 'temperature');
            $replace['#temperature#'] = $cmd->execCmd();
    
            $cmd = $this->getCmd(null, 'orp');
            $replace['#orp#'] = $cmd->execCmd();
    
            $cmd = $this->getCmd(null, 'ph');
            $replace['#ph#'] = $cmd->execCmd();

            $cmd = $this->getCmd(null, 'salt');
            $replace['#salt#'] = $cmd->execCmd();
    
            if( $this->getConfiguration('typeDisinfection',false) != 'salt') {

                $cmd = $this->getCmd(null, 'tds');
                $replace['#tds#'] = $cmd->execCmd();

            }
            /* ------------ N'ajouter plus de code apres ici------------ */

            $return = $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'ondilo', 'ondilo'))); 
            log::add('ondilo','debug','return: ' . print_r($return, true) );
           return $return;
        }
        
        return parent::toHtml();
    }

    public function lastMeasures() {

        $id = $this->getConfiguration( 'id', '' );
        $ondilo = new ondiloAPI();
        $ondilo->setAccessToken( config::byKey( 'access_token', 'ondilo' ) );

        $getLastMeasures  = $ondilo->getLastMeasures( $id );
        log::add('ondilo','debug','lastMeasures: '. print_r($getLastMeasures, true));

        if( is_json( $getLastMeasures) ) {

            $lastMeasures = json_decode( $getLastMeasures, true );

            foreach ( $lastMeasures as $measure ) {

                try {

                    switch ( $measure['data_type'] ) {
                        case 'battery':
                            $this->batteryStatus( $measure['value']);
                            break;
    
                        case 'rssi':
                            $this->setConfiguration( 'rf_status', $measure['value'] );
                            break;
                        
                        default:
    
                            $cmd = ondiloCmd::byEqLogicIdAndLogicalId( $this->getId(), $measure['data_type'] );
                            log::add('ondilo','debug','lastMeasures: '. print_r($cmd, true));
    
    
                            if( is_object( $cmd ) ) {
            
                                $cmd->event( $measure['value'] );
                            } 
    
                            break;
                    }

                } catch (Exception $e) {
                    
                    log::add('ondilo','debug','e : ' . print_r($e, true) );
                }



                log::add('ondilo','debug','mesure: '. $measure['data_type'] . '=' . $measure['value'] );
            }
        }
    }

    /*     * **********************Getteur Setteur*************************** */
}

class ondiloCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */


    public function execute($_options = array()) {

		if ($this->getType() == '') {
			return '';
        }
        
		$eqLogic = $this->getEqlogic();
        $logical = $this->getLogicalId();
        
        if( $logical == 'refresh' ) {
            $eqLogic->lastMeasures();
        }
        
    }

    /*     * **********************Getteur Setteur*************************** */
}