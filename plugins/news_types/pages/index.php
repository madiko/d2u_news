<?php
$func = rex_request('func', 'string');
$entry_id = rex_request('entry_id', 'int');
$message = rex_get('message', 'string');

// Print comments
if($message != "") {
	print rex_view::success(rex_i18n::msg($message));
}

// save settings
if (filter_input(INPUT_POST, "btn_save") == 1 || filter_input(INPUT_POST, "btn_apply") == 1) {
	$form = (array) rex_post('form', 'array', []);

	$success = TRUE;
	$type = FALSE;
	$type_id = $form['type_id'];
	foreach(rex_clang::getAll() as $rex_clang) {
		if($type === FALSE) {
			$type = new \D2U_News\Type($type_id, $rex_clang->getId());
			$type->type_id = $type_id; // Ensure correct ID in case first language has no object
			$type->priority = $form['priority'];
		}
		else {
			$type->clang_id = $rex_clang->getId();
		}
		$type->name = $form['lang'][$rex_clang->getId()]['name'];
		$type->translation_needs_update = $form['lang'][$rex_clang->getId()]['translation_needs_update'];
		
		if($type->translation_needs_update == "delete") {
			$type->delete(FALSE);
		}
		else if($type->save() > 0){
			$success = FALSE;
		}
		else {
			// remember id, for each database lang object needs same id
			$type_id = $type->type_id;
		}
	}

	// message output
	$message = 'form_save_error';
	if($success) {
		$message = 'form_saved';
	}
	
	// Redirect to make reload and thus double save impossible
	if(filter_input(INPUT_POST, "btn_apply") == 1 && $type !== FALSE) {
		header("Location: ". rex_url::currentBackendPage(array("entry_id"=>$type->type_id, "func"=>'edit', "message"=>$message), FALSE));
	}
	else {
		header("Location: ". rex_url::currentBackendPage(array("message"=>$message), FALSE));
	}
	exit;
}
// Delete
else if(filter_input(INPUT_POST, "btn_delete") == 1 || $func == 'delete') {
	$type_id = $entry_id;
	if($type_id == 0) {
		$form = (array) rex_post('form', 'array', []);
		$type_id = $form['type_id'];
	}
	$type = new \D2U_News\Type($type_id, rex_config::get("d2u_helper", "default_lang"));
	$type->type_id = $type_id; // Ensure correct ID in case language has no object
	
	// Check if type is used
	$uses_news = $type->getNews(FALSE);
	
	// If not used, delete
	if(count($uses_news) == 0) {
		$type->delete(TRUE);
	}
	else {
		$message = '<ul>';
		foreach($uses_news as $current_news) {
			$message .= '<li><a href="index.php?page=d2u_news/news&func=edit&entry_id='. $current_news->news_id .'">'. $current_news->name .'</a></li>';
		}
		$message .= '</ul>';

		print rex_view::error(rex_i18n::msg('d2u_helper_could_not_delete') . $message);
	}
	
	$func = '';
}

// Eingabeformular
if ($func == 'edit' || $func == 'add') {
?>
	<form action="<?php print rex_url::currentBackendPage(); ?>" method="post">
		<div class="panel panel-edit">
			<header class="panel-heading"><div class="panel-title"><?php print rex_i18n::msg('d2u_news_types_type'); ?></div></header>
			<div class="panel-body">
				<input type="hidden" name="form[type_id]" value="<?php echo $entry_id; ?>">
				<?php
					foreach(rex_clang::getAll() as $rex_clang) {
						$type = new \D2U_News\Type($entry_id, $rex_clang->getId());
						$required = $rex_clang->getId() == rex_config::get("d2u_helper", "default_lang") ? TRUE : FALSE;
						
						$readonly_lang = TRUE;
						if(\rex::getUser()->isAdmin() || (\rex::getUser()->hasPerm('d2u_news[edit_lang]') && \rex::getUser()->getComplexPerm('clang')->hasPerm($rex_clang->getId()))) {
							$readonly_lang = FALSE;
						}
				?>
					<fieldset>
						<legend><?php echo rex_i18n::msg('d2u_helper_text_lang') .' "'. $rex_clang->getName() .'"'; ?></legend>
						<div class="panel-body-wrapper slide">
							<?php
								if($rex_clang->getId() != rex_config::get("d2u_helper", "default_lang")) {
									$options_translations = [];
									$options_translations["yes"] = rex_i18n::msg('d2u_helper_translation_needs_update');
									$options_translations["no"] = rex_i18n::msg('d2u_helper_translation_is_uptodate');
									$options_translations["delete"] = rex_i18n::msg('d2u_helper_translation_delete');
									d2u_addon_backend_helper::form_select('d2u_helper_translation', 'form[lang]['. $rex_clang->getId() .'][translation_needs_update]', $options_translations, [$type->translation_needs_update], 1, FALSE, $readonly_lang);
								}
								else {
									print '<input type="hidden" name="form[lang]['. $rex_clang->getId() .'][translation_needs_update]" value="">';
								}
							?>
							<script>
								// Hide on document load
								$(document).ready(function() {
									toggleClangDetailsView(<?php print $rex_clang->getId(); ?>);
								});

								// Hide on selection change
								$("select[name='form[lang][<?php print $rex_clang->getId(); ?>][translation_needs_update]']").on('change', function(e) {
									toggleClangDetailsView(<?php print $rex_clang->getId(); ?>);
								});
							</script>
							<div id="details_clang_<?php print $rex_clang->getId(); ?>">
								<?php
									d2u_addon_backend_helper::form_input('d2u_helper_name', "form[lang][". $rex_clang->getId() ."][name]", $type->name, $required, $readonly_lang, "text");
								?>
							</div>
						</div>
					</fieldset>
				<?php
					}
				?>
				<fieldset>
					<legend><?php echo rex_i18n::msg('d2u_helper_data_all_lang'); ?></legend>
					<div class="panel-body-wrapper slide">
						<?php
							// Do not use last object from translations, because you don't know if it exists in DB
							$type = new \D2U_News\Type($entry_id, rex_config::get("d2u_helper", "default_lang"));
							$readonly = TRUE;
							if(\rex::getUser()->isAdmin() || \rex::getUser()->hasPerm('d2u_news[edit_data]')) {
								$readonly = FALSE;
							}
							
							d2u_addon_backend_helper::form_input('header_priority', 'form[priority]', $type->priority, TRUE, $readonly, 'number');
						?>
					</div>
				</fieldset>
			</div>
			<footer class="panel-footer">
				<div class="rex-form-panel-footer">
					<div class="btn-toolbar">
						<button class="btn btn-save rex-form-aligned" type="submit" name="btn_save" value="1"><?php echo rex_i18n::msg('form_save'); ?></button>
						<button class="btn btn-apply" type="submit" name="btn_apply" value="1"><?php echo rex_i18n::msg('form_apply'); ?></button>
						<button class="btn btn-abort" type="submit" name="btn_abort" formnovalidate="formnovalidate" value="1"><?php echo rex_i18n::msg('form_abort'); ?></button>
						<?php
							if(\rex::getUser()->isAdmin() || \rex::getUser()->hasPerm('d2u_news[edit_data]')) {
								print '<button class="btn btn-delete" type="submit" name="btn_delete" formnovalidate="formnovalidate" data-confirm="'. rex_i18n::msg('form_delete') .'?" value="1">'. rex_i18n::msg('form_delete') .'</button>';
							}
						?>
					</div>
				</div>
			</footer>
		</div>
	</form>
	<br>
	<?php
		print d2u_addon_backend_helper::getCSS();
		print d2u_addon_backend_helper::getJS();
}

if ($func == '') {
	$query = 'SELECT types.type_id, name, priority '
		. 'FROM '. \rex::getTablePrefix() .'d2u_news_types AS types '
		. 'LEFT JOIN '. \rex::getTablePrefix() .'d2u_news_types_lang AS lang '
			. 'ON types.type_id = lang.type_id AND lang.clang_id = '. rex_config::get("d2u_helper", "default_lang") .' ';
	if($this->getConfig('default_type_sort') == 'priority') {
		$query .= 'ORDER BY priority ASC';
	}
	else {
		$query .= 'ORDER BY name ASC';
	}
    $list = rex_list::factory($query, 1000);

    $list->addTableAttribute('class', 'table-striped table-hover');

    $tdIcon = '<i class="rex-icon fa-file-text-o"></i>';
 	$thIcon = "";
	if(\rex::getUser()->isAdmin() || \rex::getUser()->hasPerm('d2u_news[edit_data]')) {
		$thIcon = '<a href="' . $list->getUrl(['func' => 'add']) . '" title="' . rex_i18n::msg('add') . '"><i class="rex-icon rex-icon-add-module"></i></a>';
	}
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['func' => 'edit', 'entry_id' => '###type_id###']);

    $list->setColumnLabel('type_id', rex_i18n::msg('id'));
    $list->setColumnLayout('type_id', ['<th class="rex-table-id">###VALUE###</th>', '<td class="rex-table-id">###VALUE###</td>']);

    $list->setColumnLabel('name', rex_i18n::msg('d2u_news_name'));
    $list->setColumnParams('name', ['func' => 'edit', 'entry_id' => '###type_id###']);

	$list->setColumnLabel('priority', rex_i18n::msg('header_priority'));

    $list->addColumn(rex_i18n::msg('module_functions'), '<i class="rex-icon rex-icon-edit"></i> ' . rex_i18n::msg('edit'));
    $list->setColumnLayout(rex_i18n::msg('module_functions'), ['<th class="rex-table-action" colspan="2">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(rex_i18n::msg('module_functions'), ['func' => 'edit', 'entry_id' => '###type_id###']);

	if(\rex::getUser()->isAdmin() || \rex::getUser()->hasPerm('d2u_news[edit_data]')) {
		$list->addColumn(rex_i18n::msg('delete_module'), '<i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('delete'));
		$list->setColumnLayout(rex_i18n::msg('delete_module'), ['', '<td class="rex-table-action">###VALUE###</td>']);
		$list->setColumnParams(rex_i18n::msg('delete_module'), ['func' => 'delete', 'entry_id' => '###type_id###']);
		$list->addLinkAttribute(rex_i18n::msg('delete_module'), 'data-confirm', rex_i18n::msg('d2u_helper_confirm_delete'));
	}

    $list->setNoRowsMessage(rex_i18n::msg('d2u_news_types_no_types_found'));

    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('d2u_news_types'), false);
    $fragment->setVar('content', $list->get(), false);
    echo $fragment->parse('core/page/section.php');
}