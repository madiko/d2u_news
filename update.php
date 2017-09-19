<?php
// Update language replacements
d2u_references_lang_helper::factory()->install();

// Update modules
if(class_exists(D2UModuleManager) && class_exists(D2UNewsModules)) {
	$modules = [];
	$modules[] = new D2UModule("40-1",
		"D2U News - Ausgabe News",
		1);
	$modules[] = new D2UModule("40-2",
		"D2U News - Ausgabe Messen",
		1);
	$modules[] = new D2UModule("40-3",
		"D2U News - Ausgabe News und Messen",
		1);
	$d2u_module_manager = new D2UModuleManager($modules, "", "d2u_news");
	$d2u_module_manager->autoupdate();
}