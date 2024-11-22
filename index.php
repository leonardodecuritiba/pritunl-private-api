<?php
require './PritunlHelper.php'; // Ensure the PritunlHelper class is included
$pritunl = new PritunlHelper('username', 'password', 'https://redeprivada.tec.br:7443', 'redeprivada.tec.br:7443');

$organization_id = 1;
//Get all users of an organization
$pritunl->getAllUsers($organization_id, $page);
/*
//Create user
$pritunl->create_user('username', 'organization_id', 'password_nullable');

//Get users list of an organization
$pritunl->getUsers($organization_id, $page); //page has default value 0

//Get all users of an organization
$pritunl->getAllUsers($organization_id, $page);

//Get user files list
$pritunl->getUserFiles($organization_id, $user_id);

//Disable User
$pritunl->disableUser($user_id, $organization_id);

//Enable User
$pritunl->enableUser($user_id, $organization_id);

//Delete User
$pritunl->deleteUser($user_id, $organization_id);

//Get servers list
$pritunl->get_servers();

//Get organizations list
$pritunl->getOrganization($page); //page has default value 0

//Get hosts
$pritunl->get_hosts($page); //page has default value 0

//Get attached organization to a server
$pritunl->get_server_organization($server_id);
*/