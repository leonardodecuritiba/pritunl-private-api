<?php
require './vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;

class PritunlHelper
{

    private $username, $password, $url, $session, $csrf, $cookie_url;
    private $client;

    public static function get_instance($data)
    {
        return new self($data['username'], $data['password'], $data['base_url'], $data['url']);
    }

    public function __construct($username, $password, $url, $cookie_url)
    {
        $this->username = $username;
        $this->password = $password;
        $this->url = $url;
        $this->cookie_url = $cookie_url;

        $this->session = $this->load_session();
        $this->check_auth();
    }

    public function load_session()
    {
        try {
            return file_get_contents(__DIR__ . '/session.txt');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function store_session($session)
    {
        file_put_contents(__DIR__ . '/session.txt', $session);
    }

    public function check_auth()
    {
        if (!$this->getCSRF()) {
            $this->login();
        }
    }

    public function get_full_url($endpoint)
    {
        return $this->url . $endpoint;
    }

    public function login()
    {
        $client = new Client(['verify' => false]);

        $req = $client->post($this->get_full_url('/auth/session'), [
            'content-type' => 'application/json',
            'json' => [
                'username' => $this->username,
                'password' => $this->password
            ]
        ]);

        dd($req);

        if ($req->getStatusCode() == 401) {
            //            Log::error('Pritunl login info is wrong !!!');
        }

        if ($req->getStatusCode() == 200) {
            $this->store_login_session($req);
            $this->getCSRF();
        }
    }

    public function store_login_session(Response $req)
    {
        $cookie = $req->getHeader('Set-Cookie');
        $session = explode(';', explode('=', $cookie[0])[1])[0];
        $this->session = $session;
        $this->store_session($session);
    }

    public function getCookie()
    {
        return CookieJar::fromArray(['session' => $this->session], $this->cookie_url);
    }

    public function getCSRF()
    {
        $client = new Client(['verify' => false]);

        try {
            $req = $client->request('GET', $this->get_full_url('/state'), [
                'content-type' => 'application/json',
                'cookies' => $this->getCookie()
            ]);

            $data = json_decode($req->getBody(), true);

            $this->csrf = $data['csrf_token'];

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function SendRequest($method, $path, $data = null)
    {
        $client = new Client(['verify' => false]);

        $options = [
            'cookies' => $this->getCookie(),
            'headers' => [
                'csrf-token' => $this->csrf
            ],
        ];

        if ($data != null) {
            $options['json'] = $data;
        }

        $req = $client->request($method, $this->get_full_url($path), $options);

        $this->store_login_session($req);

        return json_decode($req->getBody(), true);
    }

    public function SendUnDecodeRequest($method, $path, $data = null)
    {
        $client = new Client(['verify' => false]);

        $options = [
            'cookies' => $this->getCookie(),
            'headers' => [
                'csrf-token' => $this->csrf
            ],
        ];

        if ($data != null) {
            $options['json'] = $data;
        }

        $req = $client->request($method, $this->get_full_url($path), $options);

        $this->store_login_session($req);

        return $req->getBody();
    }

    ///------------- functions

    public function getOrganization($page = 0)
    {
        return $this->SendRequest('get', '/organization?page=' . $page);
    }

    public function getUsers($organization_id, $page = 0)
    {
        return $this->SendRequest('get', '/user/' . $organization_id . '?page=' . $page);
    }

    public function getAllUsers($organization_id)
    {
        $pages = $this->getUsers($organization_id)['page_total'];

        $users = [];

        for ($page = 0; $page <= $pages; $page++) {
            $users = array_merge($users, $this->getUsers($organization_id, $page)['users']);
        }

        return $users;
    }

    public function getUserFiles($org_id, $user_id)
    {
        return $this->SendRequest('get', '/key/' . $org_id . '/' . $user_id);
    }

    public function getUserTarFile($org_id, $user_id)
    {
        return $this->SendUnDecodeRequest('get', '/key/' . $org_id . '/' . $user_id . '.tar');
    }

    public function create_user($username, $org_id, $password = null)
    {
        $data = $this->get_pritunl_new_user_array_data($username, $org_id, $password);

        return $this->SendRequest('post', '/user/' . $org_id, $data);
    }

    private function get_pritunl_new_user_array_data($username, $org_id, $password = null)
    {
        return [
            "id" => null,
            "organization" => $org_id,
            "organization_name" => null,
            "name" => $username,
            "email" => null,
            "groups" => [],
            "gravatar" => null,
            "audit" => null,
            "type" => null,
            "auth_type" => "local",
            "yubico_id" => "",
            "status" => null,
            "sso" => null,
            "otp_auth" => null,
            "otp_secret" => null,
            "servers" => null,
            "disabled" => null,
            "network_links" => [],
            "dns_mapping" => null,
            "bypass_secondary" => false,
            "client_to_client" => false,
            "dns_servers" => [],
            "dns_suffix" => "",
            "port_forwarding" => [],
            "pin" => $password,
            "mac_addresses" => []
        ];
    }

    public function FindUserData($user_id, $org_id)
    {
        $data = $this->getUsers($org_id);

        $all_pages = $data['page_total'];
        $page = 1;
        $find_user = null;
        while (true) {
            $page++;

            foreach ($data['users'] as $user) {
                if ($user['id'] == $user_id) {
                    $find_user = $user;
                    break;
                }
            }

            if ($find_user != null) {
                break;
            }

            if ($page > $all_pages) {
                break;
            }

            $data = $this->getUsers($org_id, $page);
        }

        return $find_user;
    }

    public function disableUser($user_id, $org_id)
    {
        $data = $this->FindUserData($user_id, $org_id);

        $data['disabled'] = true;

        return $this->SendRequest('put', '/user/' . $org_id . '/' . $user_id, $data)['disabled'];
    }

    public function enableUser($user_id, $org_id)
    {
        $data = $this->FindUserData($user_id, $org_id);

        $data['disabled'] = false;

        return !$this->SendRequest('put', '/user/' . $org_id . '/' . $user_id, $data)['disabled'];
    }

    public function deleteUser($user_id, $org_id)
    {
        $this->SendRequest('delete', '/user/' . $org_id . '/' . $user_id);
    }

    public function get_servers()
    {
        return $this->SendRequest('get', '/server?page=0');
    }

    public function get_hosts($page = 0)
    {
        return $this->SendRequest('get', '/host?page=' . $page);
    }

    public function get_usage($host_id)
    {
        return $this->SendRequest('get', '/host/' . $host_id . '/usage/1m');
    }

    public function get_server_organization($server_id)
    {
        return $this->SendRequest('get', '/server/' . $server_id . '/organization');
    }

    public function get_output($server_id)
    {
        return $this->SendRequest('get', '/server/' . $server_id . '/output');
    }

    public static function get_users_action_from_output($output)
    {
        $connected = [];
        $disconnected = [];
        foreach ($output as $text) {
            $discon = explode('User disconnected user_id=', $text);

            if (count($discon) > 1) {
                $disconnected[] = $discon[1];
            } else {
                $con = explode('User connected user_id=', $text);

                if (count($con) > 1) {
                    $connected[] = $con[1];
                }
            }
        }

        return [$connected, $disconnected];
    }
}
