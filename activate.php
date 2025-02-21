<?php
/***************************************
* poMMo Activation File
* Loads needed libraries and checks db
***************************************/

define('_poMMo_support','http://pommo.org/');

require_once (dirname(__FILE__).'/bootstrap.php');
require_once (bm_baseDir.'/classes/Pommo_Install.php');

session_start();
session_unset();
session_destroy();

$logger->debug('poMMo activating...');

$dbpass = '';
$dbhost = 'localhost';
$dbuser = '';
$dbport = '';
$dbtype = 'mysql';
$prefix = 'pommo_';
$dbname = '';

$template->assign('dbpass',$dbpass);
$template->assign('dbhost',$dbhost);
$template->assign('dbuser',$dbuser);
$template->assign('dbport',$dbport);
$template->assign('dbtype',$dbtype);
$template->assign('prefix',$prefix);
$template->assign('dbname',$dbname);
$template->assign('support', _poMMo_support);

if(!empty($_POST)) {
    $logger->debug('post dump: ' . print_r($_POST,true));

    // get posted vars
    $dbhost = $_POST['dbhost'];
    $dbuser = $_POST['dbuser'];
    $dbpass = $_POST['dbpass'];
    $dbport = $_POST['dbport'];
    $dbtype = $_POST['dbtype'];
    $dbname = $_POST['dbname'];
    $prefix = $_POST['prefix'];

    if(strtolower($dbtype) != 'mysql' && strtolower($dbtype) != 'mysqli') {
        $logger->addMsg("Unsupported Database Type -- only MySQL is supported", 1);
    }

    // (Opcional) construir DSN caso queiras usar Connect($dsn)
    /*
    $dsn = (strtolower($dbtype) == 'mysql')
        ? 'mysql://' . $dbuser . ':' . $dbpass . '@' . $dbhost . '/' . $dbname
        : 'mysqli://' . $dbuser . ':' . $dbpass . '@' . $dbhost . '/' . $dbname;
    */

    include_once (bm_baseDir.'/inc/adodb/adodb.inc.php'); // require ADODB

    // Removido o & (incompatível com versões modernas de PHP)
    $dbobject = ADONewConnection($dbtype);

    // Removido @ para não suprimir erros
    $connected = $dbobject->Connect($dbhost, $dbuser, $dbpass, $dbname);

    if(!$connected || !$dbobject->IsConnected()) {
        $logger->addMsg(
            "The information you provided does not allow a connection to the database. ".
            "Check username, password, and database name.",
            1
        );
    }

    if(!$logger->isErr()) {
        $install = new Pommo_Install($dbobject);
        $install->dbtype = strtolower($dbtype);
        $install->prefix = $prefix;

        // check if database is installed or incomplete
        $data = $install->isInstalled();

        // if installed, prompt user if they want to upgrade
        if($data == 'complete' || $data == 'incomplete') {
            // currently cannot upgrade incomplete
            if($data == 'incomplete') {
                $logger->addMsg(
                    "You have an incomplete database installation. ".
                    "If you want to start fresh, drop all existing tables before continuing.",
                    1
                );
            }
            else {
                $logger->addMsg(
                    "It appears you already have a complete database installation. ".
                    "Upgrading an existing database is not supported at this time. ".
                    "If you want to start fresh, drop all existing tables before continuing.",
                    1
                );
            }
        }
        else {
            // we can proceed with the activation.
            // DB exists? If not, attempt to create
            if(!$install->dbExists()) {
                $logger->addMsg(
                    "The database you specified (" . $dbname . ") does not exist. ".
                    "Attempting to create it. If this fails, you must create the database yourself ".
                    "(using a tool like phpMyAdmin or cPanel) or have your hosting provider do so."
                );

                if(!$install->dbCreate($dbname)) {
                    $logger->addMsg(
                        "Database creation attempt failed. ".
                        "Check permissions or contact your hosting provider.", 
                        1
                    );
                }
                else {
                    $dbobject->Close();
                    // novamente, removido @
                    $dbobject->Connect($dbhost, $dbuser, $dbpass, $dbname);
                }
            }

            if(!$logger->isErr()) {
                // create tables
                if(!$install->createTables()) {
                    $logger->addMsg(
                        "Error occured while trying to create tables. Perhaps they already exist?", 
                        1
                    );
                }
                else {
                    // success, DB is installed. Create config file
                    if($install->writeConfig($dbhost,$dbuser,$dbpass,$dbname,$dbtype,$prefix,$dbport)) {
                        $logger->addMsg(
                            "Database installed successfully! ".
                            "Configuration file has been written. ".
                            "You can now proceed to the next step!"
                        );
                        Pommo::redirect('install.php');
                    }
                    else {
                        $logger->addMsg(
                            "Database installed successfully, but could not write config file. ".
                            "Copy the following into your config.php file:",
                            1
                        );
                        $template->assign('configStr',$install->_configStr);
                    }
                }
            }
        }
    }

    $template->assign('dbpass',$dbpass);
    $template->assign('dbhost',$dbhost);
    $template->assign('dbuser',$dbuser);
    $template->assign('dbport',$dbport);
    $template->assign('dbtype',$dbtype);
    $template->assign('prefix',$prefix);
    $template->assign('dbname',$dbname);
}

$template->display('activate');
?>
