/*jslint nomen: true, browser: true */
/*global jQuery: false, console: false */

var directEditMenu, directMenuSaveButton;

(function ($) {
	"use strict";
	directEditMenu = function (globalOptions) {
		var selectionPath, initMenus;;
		
		selectionPath = globalOptions.selectionPath;
		var itemListTotal = []; // list of rendered menu items containing a reference to the tree item
		
		var getNewItems = function(groups) { 
			var group, item, items, htmlOutput = '', reference;
			htmlOutput += '<div class="direct-menu-trash">trash</div>';
			htmlOutput += '<div class="direct-menu-accordion">'
			for (group in groups) {
				htmlOutput += '<h3>' + groups[group].name + '</h3>';
				if (itemListTotal.indexOf(groups[group]) === -1) {
					itemListTotal.push(groups[group]);
				}
				reference = itemListTotal.indexOf(groups[group]);
				items = groups[group].items;
				htmlOutput += '<ul>';
				for (item in items) {
					htmlOutput += '<li data-remove-from-parent="false" data-parent-reference="' + reference + '" data-index="' + item + '"><a>' + items[item].name + '</a></li>';
				}
				htmlOutput += '</ul>';
			}
			htmlOutput += '</div>';
			return htmlOutput;
		};
		$('.direct-menu-new-items').html(getNewItems(globalOptions['new']));
		$( '.direct-menu-new-items li' ).draggable({
			helper: 'clone',
			connectToSortable: ".direct-menu-sortable"
		});
		$('.direct-menu-accordion').accordion();
		
		initMenus = function() {
			var itemList;
			
			itemList= itemListTotal.slice(0);  // create a shallow copy
			jQuery('.direct-editable-menu').each(function () {
				var element, options;
				element = $(this);
				options = JSON.parse(element.attr('data-local-options'));
				options.classSelected = 'menu-item-active';  // temp
				
				var getMenu = function(items, item, startLevel, depth, level, path) {
					var htmlOutput = '', reference, uiClass, placeholder;
					level = level || 0;
					if (itemList.indexOf(items[item]) === -1) {
						itemList.push(items[item]);
					}
					reference = itemList.indexOf(items[item]);
					items = items[item].items;
					path = path ? path + ',' + item : '&quot;' + item + '&quot;';
					if (level < startLevel) {
						// find selectionPath child item and delegate output
						if (level + 1 < selectionPath.length ) {
							item = selectionPath[level + 1];
						}
						if (item !== undefined && items && items[item]) {
							htmlOutput = getMenu(items, item, startLevel, depth, level + 1, path);
						}
					} else {
						// output children recursively
						if (items && items.length > 0 ) {
							uiClass = 'class="direct-menu-sortable" ';
						} else {
							if (level + 1 === selectionPath.length) { // new menus can only be created one level below an existing one
								uiClass = 'class="direct-menu-droppable" ';
								placeholder = '<li class="direct-menu-placeholder"><a>new</a></li>';
							}
						}
						htmlOutput += '<ul ' + uiClass + 'data-path="' + path + '"data-reference="' + reference + '">';
						if (placeholder) htmlOutput += placeholder;
						for (item in items) {
							var cssClass = '';
							if (item == selectionPath[level + 1]) {
								cssClass = ' class="' + options.classSelected + '"';
							}
							htmlOutput += '<li' + cssClass + ' data-remove-from-parent="true" data-path="' + path + ',' + item + '"data-parent-reference="' + reference + '" data-index="' + item + '">';
							htmlOutput += '<a>' + items[item].name + '</a>';
							if (depth === 0 || depth === -1 || depth === undefined || level < (startLevel + depth - 1)) {
								// todo depth = -1 should flatten the menu to one level
								// get the childs children
								htmlOutput += getMenu(items, item, startLevel, depth, level + 1, path);
							}
							htmlOutput += '</li>';
						}
						htmlOutput += '</ul>';
					}
					return htmlOutput;
				}
				element.html(getMenu(globalOptions.menus, options.menu, options.startLevel, options.depth));
			});
			$('.direct-menu-sortable').sortable({
				'connectWith':'.direct-menu-sortable',
				stop: function( e, ui ) {
					var  parentItemOld, indexOld, toIndex, toParent, item; // these all refer to the data tree (not the DOM)
					parentItemOld = itemList[ui.item.attr('data-parent-reference')];
					indexOld = ui.item.attr('data-index');
					item = parentItemOld.items[indexOld];
					if (ui.item.attr('data-remove-from-parent') === 'true') {
						parentItemOld.items.splice(indexOld, 1);
					} 
					toIndex = ui.item.index();
					toParent = itemList[ui.item.parent().attr('data-reference')];
					if (item) {
						toParent.items.splice(toIndex,0,item);
					}
					// todo, does not work correctly when removing the item influences the path
					// todo remove onclick event
					ui.item.attr('data-path',ui.item.parent().attr('data-path') + ',' + toIndex);
					initMenus();
				}
			});
			$('.direct-editable-menu li').click(function() {
				if ($(this).attr('data-path')) {
					selectionPath = JSON.parse('[' + $(this).attr('data-path') + ']');
					initMenus();
				}
			});
			$('.direct-menu-droppable').droppable({
				hoverClass: "drop-hover" ,
				drop: function( e, ui ) {
					var parentItemOld, indexOld, item, toParent; 
					
					parentItemOld = itemList[ui.draggable.attr('data-parent-reference')];
					indexOld = ui.draggable.attr('data-index');
					item = parentItemOld.items[indexOld];
					toParent = itemList[$(this).attr('data-reference')]
					toParent.items = [item];
					initMenus();
				}
			});
			$('.direct-menu-trash').droppable({
				hoverClass: "drop-hover" ,
				drop: function( e, ui ) {
					var parentItemOld, indexOld; 
					
					parentItemOld = itemList[ui.draggable.attr('data-parent-reference')];
					indexOld = ui.draggable.attr('data-index');
					if (ui.draggable.attr('data-remove-from-parent') === 'true') {
						parentItemOld.items.splice(indexOld, 1);
					} 
					initMenus();
				}
			});		
		};
		initMenus();
		$.fn.directMenuSaveButton = function () {
			var data = {};
			
			data.action = "direct-save-menu";
			data.menus = globalOptions.menus;
			$(this).click(function () {
				$.ajax({
					url: globalOptions.ajaxUrl,
					type: 'POST',
					error: function () {
						alert('Error saving.');
					},
					dataType: 'json',
					success: function (result) {
						alert('A miracle just happened.');
					},
					data: data
				});
			});
		};
	};
}(jQuery));
