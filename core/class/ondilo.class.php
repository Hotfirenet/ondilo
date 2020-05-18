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

        $redirect_uri = network::getNetworkAccess('internal') . '/plugins/ondilo/core/api/ondilo.php?action=autorize';
        $state = config::genKey(17);
        config::save('state', $state, 'ondilo'); 

        $ondilo = new ondiloAPI();
        return $ondilo->getAuthorizationCode( $redirect_uri, $state );
    }

    public static function getToken( $_code ) {

        $redirect_uri = network::getNetworkAccess('internal') . '/plugins/ondilo/core/api/ondilo.php?action=autorize';

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

        if( is_object( $eqLogics ) ) {

            foreach( $eqLogics as $eqLogic ) {

                $eqLogic->lastMeasures();
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
                    $eqLogic->setConfiguration( 'id'    , $pool['id'] );
                    $eqLogic->setConfiguration( 'type'  , $pool['type'] );
                    $eqLogic->setConfiguration( 'volume', $pool['volume'] );

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

                $commands = $eqLogic->getCommands();

                foreach ( $commands['commands'] as $command ) {

                    try {
        
                       $eqLogic->createCmd( $command );
        
                    } catch (Exception $e) {
        
                        return false;
                    }
                }

                $eqLogic->lastMeasures();

                $image = $eqLogic->getImage();
                $eqLogic->setConfiguration( 'image', $image );
                log::add( 'ondilo', 'debug', 'image ' . $image );
                $eqLogic->save();

                event::add('jeedom::alert', array(
                    'level' => 'success',
                    'page' => 'ondilo',
                    'message' => __('Ico inclus avec succès : ' . $pool['name'] , __FILE__),
                ));
                return true;
            }
        }
    }

    /*     * *********************Méthodes d'instance************************* */

    private function getCommands() {

        $path = dirname(__FILE__) . '/../config/';

        if (!is_dir($path)) {

            return false;
        }

        try {

            //$file = $path . $this->getConfiguration('type',false) .'.json';
            $file = 'commands.json';
            $content = file_get_contents( $file );
            return json_decode( $content, true);
            
        } catch (Exception $e) {

            return false;
        }
        
        return false;	        
    }

	private function createCmd( $_cmd ) {

        $newCmd = $this->getCmd( null, $_cmd['logicalId'] );
        
		if ( ! is_object( $newCmd ) ) {

			log::add('ondilo','debug','Création commande:'.$_cmd['logicalId']);
			$newCmd = new ondiloCmd();
			$newCmd->setLogicalId( $_cmd['logicalId'] );
			$newCmd->setIsVisible( $_cmd['isVisible'] );
			$newCmd->setName(__( $_cmd['name'], __FILE__ ));
            $newCmd->setEqLogic_id( $this->getId() );
            
        } 
        
		if( isset( $cmd['unit'] ) ) {

			$newCmd->setUnite( $_cmd['unit'] );
        }
        
        $newCmd->setType($_cmd['type']);
        
		if( isset( $_cmd['configuration'] ) ) {

			foreach($_cmd['configuration'] as $configuration_type => $configuration_value) {

				$newCmd->setConfiguration( $configuration_type, $configuration_value );
			}
        } 
        
		if( isset( $_cmd['template'] ) ) {

			foreach($_cmd['template'] as $template_type => $template_value) {

				$newCmd->setTemplate( $template_type, $template_value );
			}

        } 
        
		if( isset( $_cmd['display'] )) {

			foreach($_cmd['display'] as $display_type => $display_value) {
				$newCmd->setDisplay( $display_type, $display_value );
			}
        }
        
		$newCmd->setSubType( $_cmd['subtype'] );
        
		$newCmd->save();
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

                if( $measure['data_type'] == 'battery' || $measure['data_type'] == 'rssi' ) {
                    continue;
                }

                $cmd = ondiloCmd::byEqLogicIdAndLogicalId( $this->getId(), $measure['data_type'] );

                if( is_object( $cmd ) ) {

                    $cmd->event( $measure['value'] );
                    log::add('ondilo','debug','mesure: '. $measure['data_type'] . '=' . $measure['value'] );

                } else {

                    log::add('ondilo', 'error', 'Pas de commande avec ' . $this->getId() . ' ' . $measure['data_type'] );
                }
            }
        }
    }

	public function getImage($_which = 'grid', $_ver = '@2x') {
 
        $type    = $this->getConfiguration('type',false);
        $default = 'plugins/' . $this->plugin . '/plugin_info/' . $this->plugin . '_icon.png';

        if( in_array( $type, $this->type ) ) {

            if( strpos( $type, 'pool' ) ) {
                $img = 'pool';
            } else {
                $img = 'spa';
            }

            $file = sprintf( 'plugins/' . $this->plugin . '/core/config/%s.png', $img );
            if( file_exists( $file ) ) {
                return $file;
            }
        }

        return $default;
    }

    public function toHtml($_version = 'dashboard') {

        return parent::toHtml();
        
        $replace = $this->preToHtml($_version);
        if (!is_array($replace)) {
            return $replace;
        }
        $version = jeedom::versionAlias($_version);
        if ($this->getDisplay('hideOn' . $version) == 1) {
            return '';
        }
        /* ------------ Ajouter votre code ici ------------*/
        $replace['#icoImage#'] = $this->getImage();
        /* ------------ N'ajouter plus de code apres ici------------ */
        
       // return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'ondilo', 'ondilo')));
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