<?php
$capabilities = array(
	'block/la_dashboard:myaddinstance' => array(
		'captype' => 'write',
		'contextlevel' => CONTEXT_SYSTEM,
		'archetypes' => array(
			'user' => CAP_ALLOW
		),
		'clonepermissionsfrom' => 'moodle/my:manageblocks'
	),
	
	'block/la_dashboard:addinstance' => array(
		'riskbitmask' => RISK_SPAM | RISK_XSS,
		'captype' => 'write',
		'contextlevel' => CONTEXT_SYSTEM,
		'archetypes' => array(
			'editingteacher' => CAP_ALLOW,
			'manager' => CAP_ALLOW
		),
		'clonepermissionsfrom' => 'moodle/site:manageblocks'
	),
);
?>