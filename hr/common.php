<?php
// session já iniciada por session.php — não chamar session_start() aqui

if(isSet($_GET['lang']))
	{
	$lang = $_GET['lang'];
	// register the session and set the cookie
	$_SESSION['lang'] = $lang;
	setcookie('lang', $lang, time() + (3600 * 24 * 30));
	}
	else if(isSet($_SESSION['lang']))
	{
	$lang = $_SESSION['lang'];
	}
	else if(isSet($_COOKIE['lang']))
	{
	$lang = $_COOKIE['lang'];
	}
	else
	{
	$lang = 'en';
	}
	
	switch ($lang) 
	{
	case 'en':
	$lang_file = 'lang.en.php';
	break;
	case 'pt':
	$lang_file = 'lang.pt.php';
	break;
	default:
	$lang_file = 'lang.en.php';
	}
// echo $lang_file;
include_once __DIR__ . '/lang/' . $lang_file;
?>