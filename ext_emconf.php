<?php
/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
	'title' => 'Formhandler Subscription',
	'description' => 'Provides additional classes for the formhandler extension to build (newsletter) subscribe and modify / unsubscripe forms. It comes with some YAML based example templates.',
	'category' => 'plugin',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'clearCacheOnLoad' => 0,
	'author' => 'Alexander Stehlik',
	'author_email' => 'alexander.stehlik.deleteme@gmail.com',
	'author_company' => '',
	'version' => '1.0.3',
	'constraints' => [
		'depends' => [
			'php' => '>=5.5.0',
			'typo3' => '7.6.0-8.99.99',
			'formhandler' => '',
			'authcode' => '',
		],
		'conflicts' => [],
		'suggests' => [
			'tt_address' => '',
			'tinyurls' => '',
		],
	],
];
