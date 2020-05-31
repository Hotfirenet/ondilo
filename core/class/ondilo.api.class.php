<?php
/* 
 * Author: Johan VIVIEN
 * Since: 1.0
 *
*/

class ondiloAPI {

    private $url           = 'https://interop.ondilo.com';
    private $access_token  = '';

    private $authorize       = '/oauth2/authorize';
    private $token           = '/oauth2/token';
    private $pools           = '/api/customer/v1/pools';
    private $device          = '/api/customer/v1/pools/%d/device';
    private $configuration   = '/api/customer/v1/pools/%d/configuration';
    private $lastmeasures    = '/api/customer/v1/pools/%d/lastmeasures';
    private $recommendations = '/api/customer/v1/pools/%d/recommendations';

    public function getAuthorizationCode( $_redirect_uri, $_state ) {

        $data = array(
            'client_id'     => 'customer_api',
            'response_type'	=> 'code',
            'redirect_uri'  => $_redirect_uri,
            'scope'         => 'api',
            'state'         => $_state
        );

        return $this->url . $this->authorize . '?' . http_build_query( $data );
    }

    public function getToken( $_code, $_redirect_uri ) {

        $data = array(
            'code'         => $_code,
            'grant_type'   => 'authorization_code',
            'client_id'    => 'customer_api',
            'redirect_uri' => $_redirect_uri
        );

        $url = $this->url . $this->token;
        return $this->sendCommand( $url, 'POST', $data, 'urlencoded' );
    }

    public function refreshTokens( $_refresh_token ) {

        $data = array(
            'refresh_token' => $_refresh_token,
            'grant_type'   => 'refresh_token',
            'client_id'    => 'customer_api'
        );

        $url = $this->url . $this->token;
        return $this->sendCommand( $url, 'POST', $data, 'urlencoded' );        
    }

    public function getPools() {

        $data = array(
            'bearer' => $this->access_token
        );

        $url = $this->url . $this->pools;
        return $this->sendCommand( $url, 'GET', $data );           
    }

    public function getDevice( $_deviceId ) {

        $data = array(
            'bearer' => $this->access_token
        );

        $device =  sprintf( $this->device, $_deviceId);
        $url    = $this->url . $device;
        return $this->sendCommand( $url, 'GET', $data );           
    }    

    public function getConfiguration( $_deviceId ) {

        $data = array(
            'bearer' => $this->access_token
        );

        $configuration =  sprintf( $this->configuration, $_deviceId);
        $url           = $this->url . $configuration;
        return $this->sendCommand( $url, 'GET', $data );           
    }    

    public function getLastMeasures( $_deviceId ) {

        $data = array(
            'bearer' => $this->access_token
        );

        $lastmeasures =  sprintf( $this->lastmeasures, $_deviceId);
        $url          = $this->url . $lastmeasures;
        return $this->sendCommand( $url, 'GET', $data );           
    }    

    public function getRecommendations( $_deviceId ) {

        $data = array(
            'bearer' => $this->access_token
        );

        $recommendations =  sprintf( $this->recommendations, $_deviceId );
        $url           = $this->url . $recommendations;
        return $this->sendCommand( $url, 'GET', $data );         
    }

	/**
	 * Make API request.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param string $action     Request action.
	 * @param array  $options    Request options.
	 * @param string $auth_type  Authentication token to use. Defaults to server.
	 * @param string $method     HTTP method. Defaults to GET.
	 * @param string $return_key Array key from response to return. Defaults to null (return full response).
	 *
	 * @return array|string|bool|J_Error
	 */
    private function sendCommand($_url, $_method = 'GET', $_data = array(), $_contentType = 'json' ) {

        try {

            $request_http = new com_http( $_url );

            switch ( $_method ) {
                case 'POST':
                    if( $_contentType == 'json' ) {
                        $data = json_encode( $_data );
                        $headers[] = 'Accept: application/json'; 
                        $headers[] = 'Content-Type: application/json';
                        $headers[] = 'Content-Length: ' . strlen( $_data );
                    } else {
                        $data = http_build_query($_data);
                        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                    }

                    $request_http->setPost( $data );
                break;
                
                case 'GET': 

                    $headers = array(
                        'Accept: application/json',
                        'Accept-Charset: utf-8',
                        'Accept-Encoding: gzip, deflate'
                    );

                    if( isset( $_data['bearer'] ) ) {
                        $headers[] = 'Authorization: Bearer ' . $_data['bearer'];
                    }

                break;
            }

            log::add('ondilo','debug','url: ' . $_url);
            log::add('ondilo','debug','headers: ' . print_r($headers, true));
            log::add('ondilo','debug','data: ' . print_r($_data, true));

            $request_http->setHeader( $headers );

            return $request_http->exec(30); 
        } catch (\Throwable $th) {
            return $th;
        }
    }    

	public function setAccessToken($_access_token) {
		$this->access_token = $_access_token;
	}
}