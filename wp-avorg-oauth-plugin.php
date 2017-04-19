<?php
/**
 * Plugin Name: AVORG OAUTH PLUGIN
 * Plugin URI: https://www.audioverse.org
 * Description: Adds OAUTH2 login to WordPress that works well with Laravel Passport
 * Version 1.0
 * Author: Ki Song
 */

include 'local-define.php';

class AVOauth2 {

    protected $token = false;
    protected $userObj;
    protected $api_response;
    protected $return_uri;
    function __construct()
    {
        if (isset($_GET['token'])) {
            $this->token = 'Bearer ' . $_GET['token'];
        }
    }

    static function &init() {

        static $instance = false;
        if ( !$instance ) {
            $instance = new AVOauth2;
        }
        return $instance;
    }

    /**
     * Attempt to login. If username doesn't exist, create the user, login
     * user with only using username
     */
    private function login() {

        if ( !is_user_logged_in() && $this->loadUserData() ) {

            if (!username_exists($this->userObj->email)) {
                $password = wp_generate_password(12, true);
                $user_id = wp_create_user($this->userObj->email, $password, $this->userObj->email);
                wp_update_user(
                    array(
                        'ID' => $user_id,
                        'nickname' => $this->userObj->email
                    )
                );
            }

            // login user
            $user = get_user_by('login', $this->userObj->email);

            if (!is_wp_error($user)) {
                wp_clear_auth_cookie();
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID);
                // redirect to avoid showing token on url
                $url_parts = parse_url($_SERVER['REQUEST_URI']);
                wp_redirect($url_parts['path']);
                exit;
            }
        } // end is user_logged_in and load userData function
    } // end login method

    private function loadUserData() {

        if ( $this->token !== false) {

            $this->api_response = wp_remote_get(OUATH2__API_USER_URI, [
                'headers' => [
                    'Authorization' => $this->token
                ]
            ]);

            $response_obj = json_decode($this->api_response['body']);

            if (!isset($response_obj->status_code)) {
                if ( isset($response_obj->data->email) ) {
                    $this->userObj = $response_obj->data;
                    return true;
                }
            }
        }
        return false;
    }

    static function plugins_loaded() {

        $avOuath2 = AVOauth2::init();
        // try to login
        $avOuath2->login();
    }

    public static function login_url() {

        return '<a href="'.OAUTH2__AUTHORIZE_URI.'?client_id='.OAUTH2__CLIENT_ID.'&return_uri='.urlencode($_SERVER['REQUEST_URI']).'">Login</a>';
    }

    public static function login_button_url() {

        return '<a href="'.OAUTH2__AUTHORIZE_URI.'?client_id='.OAUTH2__CLIENT_ID.'&return_uri='.urlencode($_SERVER['REQUEST_URI']).'" class="woocommerce-Button button">Login</a>';
    }
    public static function login_url_sidebar() {

        return '<a href="'.OAUTH2__AUTHORIZE_URI.'?client_id='.OAUTH2__CLIENT_ID.'&return_uri='.urlencode($_SERVER['REQUEST_URI']).'" class="nav-top-link nav-top-not-logged-in">
                <span class="header-account-title">Login</span></a>';
    }

    public static function logout_url() {

        return '<a href="'.$_SERVER['REQUEST_URI'].'?oauth2_logout">Logout</a>';
    }
}

add_shortcode( 'oauth2_login_url', array('AVOauth2', 'login_url'));
add_shortcode( 'oauth2_login_button_url', array('AVOauth2', 'login_button_url'));
add_shortcode( 'oauth2_login_url_sidebar', array('AVOauth2', 'login_url_sidebar'));
add_shortcode( 'oauth2_logout', array('AVOauth2', 'logout_url'));
add_action( 'plugins_loaded', array( 'AVOauth2', 'plugins_loaded' ));