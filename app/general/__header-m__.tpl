<meta charset="utf-8"/>

<title><?= get_meta_details('site_title', $steps['current']['id']) ?></title>

<meta name="description" content="<?= get_meta_details('meta_description', $steps['current']['id']); ?>" />

<?php if(!empty($config['block_robots'])): ?>
<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noydir,noodp" />
<?php endif; ?>

<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<meta http-equiv="content-language" content="en-us" />
 
<meta name="apple-mobile-web-app-capable" content="yes"/>
<meta name="apple-mobile-web-app-status-bar-style" content="black"/>
<meta name="HandheldFriendly" content="true"/>
<!-- <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no"/>
 -->
<link rel="stylesheet" href="<?= $path['assets_css'] . '/app.css' ?>" />

<?php perfom_head_tag_close_actions(); ?>
