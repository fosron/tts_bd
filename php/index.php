<?php
/**
 * @name TTS
 * @author Tautvydas Tijunaitis
 * @copyright 2013
 */

ini_set('display_errors', 1);
error_reporting(E_ALL ^ E_NOTICE);

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application();
//$app['debug'] = true;
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/templates',
));

$app->register(new Silex\Provider\SessionServiceProvider());

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver' => 'pdo_mysql',
        'host' => 'localhost',
        'dbname' => 'fosron_silex',
        'user' => 'fosron_silex',
        'password' => 'gbkW1g7Q',
        'charset' => 'utf8'
    )
));

//***********//
// Funckijos //
//***********//
function slugify($text) {
    $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = strtolower($text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    return $text;
}

function log_it($action, $user_id, $db){
    $db->insert('cms_log', array('user_id' => $user_id, 'action' => $action, 'commit' => date('Y/m/d H:i:s')));  
}
// END Funkcijos //

//**********************//
// Pagrindiniai dalykai //
//**********************//
$template = array();

$template['user'] = $app['session']->get('user');

$template['menu'] = $app['db']->fetchAll("SELECT page_id,slug, name FROM cms_content WHERE status = 1 ORDER by created");

$settings_fetch = $app['db']->fetchAll("SELECT * FROM cms_settings");
$template['settings'] = array();
foreach ($settings_fetch as $set) {
    $template['settings'][$set['label']] = $set['value'];
}
// END Pagrindiniai dalykai //

//***********//
// Front end //
//***********//
$app->get('/', function() use ($app, $template) {
    $template['content'] = $app['db']->fetchAssoc("SELECT * FROM cms_content WHERE page_id = ?", array((string) $template['settings']['homepage']));
    return $app['twig']->render('main_site.html', $template);
});

$app->get('/p/{slug}', function($slug) use ($app, $template) {
    $template['content'] = $app['db']->fetchAssoc("SELECT * FROM cms_content WHERE slug = ?", array((string) $slug));
    if($template['content']['status'] == 0 && !$template['user'])
        return $app->redirect('/');
    else{
        return $app['twig']->render('main_site.html', $template);
    }
});
// Front end //

//************************************//
// Admin main screen and login/logout //
//************************************//
$app->post('/admin/login', function (Request $request) use ($app) {
    $username = $app['request']->get('username');
    $password = md5($app['request']->get('password'));
    $loginq = $app['db']->fetchAssoc("SELECT * FROM cms_users WHERE username = '" . $username . "'
    and password = '" . $password . "'");
    if ($loginq) {
        $app['session']->set('user', array('id' => $loginq['user_id'], 'username' => $username, 'type' => $loginq['type'],
        'fullname' => $loginq['fullname']));
        log_it('Prisijungta', $loginq['user_id'], $app['db']);
        return $app->redirect('/admin');
    } else {
        log_it('Bandyta nesėkmingai prisijungti IP '.$_SERVER['REMOTE_ADDR'], 0, $app['db']);
        $app['session']->setFlash('info', 'error');
        return $app->redirect('/admin');
    }
});

$app->get('/admin/logout', function (Request $request) use ($app) {
    $app['session']->clear();
    $app['session']->setFlash('info', 'logout');
    return $app->redirect('/admin');
});

$app->get('/admin', function(Request $request) use ($app, $template) {
    if (null === $user = $app['session']->get('user')) {
        if ($app['session']->hasFlash('info'))
            $arr = array('info' => $app['session']->getFlash('info'));
        else
            $arr = array();
            return $app['twig']->render('login.html', $arr);
    }else {
        if ($app['session']->hasFlash('info')){
            $template['info']=$app['session']->hasFlash('info');
        }
                
        $template['data'] = $app['db']->fetchAll("SELECT * FROM cms_content LEFT JOIN cms_users ON cms_content.created_by
        = cms_users.user_id ORDER by created");
        $user = $app['session']->get('user');
            
        return $app['twig']->render('main.html', $template);
    }
});
// END Admin main screen and login/logout //

//***************//
// Puslapiai add //
//***************//
$app->get('/admin/puslapiai/add', function() use ($app, $template) {
    $template['add'] = true;
    $template['data'] = array();
            
    return $app['twig']->render('puslapis_edit.html', $template);
});

$app->post('/admin/puslapiai/add', function(Request $request) use ($app, $template) {
    $content = $app['request']->get('content');
    $name = $app['request']->get('name');
    $slug = slugify($name);
                
    if($template['user']['type']==1) $status = $app['request']->get('status');
    else $status = 0;
                
    $app['db']->insert('cms_content', array('name' => $name, 'slug' => $slug, 'content' => stripslashes($content), 'status' => $status, 
    'created_by' => $template['user']['id'], 'created' => date('Y/m/d H:i:s')));
                
    log_it('Pridėtas puslapis '. $name, $template['user']['id'], $app['db']);
                
    $app['session']->setFlash('info', 'pageadd');
    return $app->redirect('/admin');
});
// END Puslapiai add //

//****************//
// Puslapiai edit //
//****************//
$app->get('/admin/puslapiai/edit/{id}', function($id) use ($app, $template) {
    $template['data'] = $app['db']->fetchAssoc("SELECT * FROM cms_content LEFT JOIN cms_users ON cms_content.created_by
    = cms_users.user_id WHERE cms_content.page_id = ?", array($id));
    
    if($template['user']['type']==2 && $template['data']['created_by']!=$template['user']['id']){
        return $app->redirect('/admin');
    }
    
    return $app['twig']->render('puslapis_edit.html', $template);
});

$app->post('/admin/puslapiai/edit/{id}', function($id, Request $request) use ($app, $template) {
    $content = $app['request']->get('content');
    $name = $app['request']->get('name');
    $slug = slugify($name);
    
    $up_info = array('name' => $name, 'slug' => $slug, 'content' => stripslashes($content), 'edited' => date('Y/m/d H:i:s'));
    if($template['user']['type']==1)
        $up_info['status'] = $app['request']->get('status');
    
    $app['db']->update('cms_content', $up_info, array('page_id' => $id));
            
    $template['data'] = $app['db']->fetchAssoc("SELECT * FROM cms_content LEFT JOIN cms_users ON cms_content.created_by =
    cms_users.user_id WHERE cms_content.page_id = ?", array($id));
    
    $template['saved'] = true;
            
    log_it('Redaguotas puslapis ID#'.$template['data']['page_id'].' '.$template['data']['name'],
    $template['user']['id'], $app['db']);
            
    return $app['twig']->render('puslapis_edit.html', $template);
});
// END Puslapiai edit //

//******************//
// Puslapiai delete //
//******************//
$app->get('/admin/puslapiai/delete/{id}', function($id) use ($app, $template) {
    if($id != $template['settings']['homepage'] && $template['user']['type']==1){
        $app['db']->delete('cms_content', array('page_id' => $id));
        
        log_it('Pašalintas puslapis ID#'.$id,
        $template['user']['id'], $app['db']);
        
        $app['session']->setFlash('info', 'pagedelete'); 
    }  
    return $app->redirect('/admin');
});
// END Puslapiai delete //

//************//
// Nustatymai //
//************//
$app->get('/admin/nustatymai', function() use ($app, $template) {
    $template['data'] = $app['db']->fetchAll("SELECT * FROM cms_settings");
    return $app['twig']->render('main_nustatymai.html', $template);
});

$app->post('/admin/nustatymai', function(Request $request) use ($app, $template) {
    $toupd = $app['request']->get('toupd');
    foreach($toupd as $t){
        $val = $app['request']->get($t);
        $app['db']->update('cms_settings', array('value' => stripslashes($val)), array('label' => $t));
    }
    $template['data'] = $app['db']->fetchAll("SELECT * FROM cms_settings");
    $template['saved']=true;
    return $app['twig']->render('main_nustatymai.html', $template);
});
// END Nustatymai //

//***************//
// Veiksmu logas //
//***************//
$app->get('/admin/veiksmai', function() use ($app, $template) {
    $template['data'] = $app['db']->fetchAll("SELECT * FROM cms_log LEFT JOIN cms_users ON cms_log.user_id = cms_users.user_id");
    return $app['twig']->render('main_veiksmai.html', $template);
});
// END Veiksmu logas //

/// Aaand the fun begins... ///
$app->run();