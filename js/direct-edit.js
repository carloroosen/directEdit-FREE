/*jslint nomen: true, browser: true */
/*global jQuery: false, console: false, directTranslations: false */

var directEdit, directNotify, directTranslate;

(function ($) {
	"use strict";

	$.directEdit.fn = {
		editors: {},
		counter: 0,
		globalOptions: {},
		ajaxUrl: '',
		getData: function (element) {
			var data = {}, dataAttr = /^data\-([a-z0-9\-]+)$/;
			$.each(element.get(0).attributes, function (i, attr) {
				if (dataAttr.test(attr.nodeName) && attr.nodeName !== 'data-global-options' && attr.nodeName !== 'data-local-options') {
					var key = attr.nodeName.match(dataAttr)[1];
					data[key] = attr.value;
				}
			});
			return data;
		},
		notify: function (message) {
			var notice, noticeRemove, notifyContainer;
			notifyContainer = $('#direct-notify-container');
			if (!notifyContainer.length) {
				notifyContainer = $('<div>').attr('id', 'direct-notify-container');
				$('body').append(notifyContainer);
			}
			noticeRemove = function (notice) {
				return function () {
					notice.animate({
						opacity: '0',
						height: '0px'
					}, {
						duration: 'slow',
						complete : function () {
							$(this).remove();
						}
					});
				};
			};
			notice = $('<div><p>' + message + '</p></div>')
				.addClass('notify')
				.hide()
				.fadeIn('slow');
			notifyContainer.append(notice);
			setTimeout(noticeRemove(notice), 3500);
		},
		directEdit: function (arg1, arg2) {
			var self = $.directEdit.fn;

			self.ajaxUrl = arg2 || '';
			
			function createEditor(element) {
				var optionSelector, thisOptions, thisGlobalOptions, thisLocalOptions, editorType, parentElement, additionalData, editor, link;
				optionSelector = element.attr('data-global-options');
				if (optionSelector && self.globalOptions) {
					thisOptions = {};
					if (optionSelector === 'page-options') {
						editorType = 'options';
					} else {
						thisGlobalOptions = self.globalOptions[optionSelector];
						if (element.attr('data-local-options')) {
							thisLocalOptions = JSON.parse(element.attr('data-local-options')) || {};
						}
						$.extend(true, thisOptions, thisGlobalOptions, thisLocalOptions);
						editorType = thisOptions.type;
						additionalData = self.getData(element);
					}

					switch (editorType) {
					case 'text':
						link = element.closest('a');
						if (link.length === 1) {
							link.directLinkEditor({'buttonFollowLink': true});
						} else if (thisOptions.unwrap) {
							parentElement = element.parent();
							parentElement.html(element.html());
							element = parentElement;
						}
						thisOptions.instanceID = self.counter;
						element.directTextEditor(thisOptions);
						editor = element.data("directEdit-directTextEditor");
						break;
					case 'file':
						element.directFileUploader(thisOptions);
						editor = element.data("directEdit-directFileUploader");
						break;
					case 'image':
						link = element.closest('a');
						if (link.length === 1) {
							link.directLinkEditor({'buttonFollowLink': true});
						}
						element.directImageEditor(thisOptions);
						editor = element.data("directEdit-directImageEditor");
						break;
					case 'link':
						if (thisOptions.orderCount && thisOptions.orderIndex) {
							thisOptions.buttonShow = false;
							thisOptions.buttonHide = false;
							thisOptions.buttonDelete = false;
							thisOptions.buttonSort = true;
						}
						element.directLinkEditor(thisOptions);
						editor = element.data("directEdit-directLinkEditor");
						break;
					case 'postwrapper':
						if (thisOptions.orderCount && thisOptions.orderIndex) {
							thisOptions.buttonShow = false;
							thisOptions.buttonHide = false;
							thisOptions.buttonDelete = false;
							thisOptions.buttonSort = true;
						}
						element.directPostwrapperEditor(thisOptions);
						editor = element.data("directEdit-directPostwrapperEditor");
						break;
					case 'list':
						element.directListEditor(thisOptions);
						editor = element.data("directEdit-directListEditor");
						break;
					case 'date':
						element.directDateEditor(thisOptions);
						editor = element.data("directEdit-directDateEditor");
						break;
					case 'options':
						element.directPageOptions(thisOptions);
						editor = element.data("directEdit-directPageOptions");
						break;
					}
					if (editor) {
						if (editorType === 'options') {
							self.editors['direct-page-options'] = editor;
						} else {
							editor.setAdditionalData(additionalData);
							self.editors['editor-' + self.counter] = editor;
						}
						self.counter += 1;
					}
				}
			}

			if (typeof arg1 === 'object') {
				if (arg1 instanceof jQuery) {
					// argument is a jQuery object, options and selector are assumed to be defined before
					arg1.find('.direct-editable').each(function () { createEditor(jQuery(this)); });
				} else {
					self.globalOptions = arg1;
					jQuery('.direct-editable').each(function () { createEditor(jQuery(this)); });
				}
			}
		},
		taskCount: function () {
			var e, editor, taskCount = 0;
			for (e in this.editors) {
				if (this.editors.hasOwnProperty(e)) {
					editor = this.editors[e];
					if (editor.isModified()) {
						taskCount += 1;
					}
				}
			}
			return taskCount;
		},
		saveAll: function () {
			var data = {}, taskCount = 0, e, editor, message = '', self = this;

			for (e in this.editors) {
				if (this.editors.hasOwnProperty(e)) {
					editor = this.editors[e];
					if (editor.options.alwaysSave || (typeof editor.isTouched === 'function' ? editor.isTouched() : editor.isModified())) {
						data[e] = editor.getData();
						taskCount += 1;
					}
				}
			}
			data[this.command[0]] = this.command[1];
			if (taskCount) {
				$.ajax({
					url: this.ajaxUrl,
					type: 'POST',
					error: function () {
						self.notify(directTranslate('Error saving.'));
					},
					dataType: 'json',
					success: function (result) {
						var e, successCount = 0, newEditor;
						if (typeof (result) === 'object') {
							if (result.redirect) {
								$(window).off('beforeunload');
								window.location.replace(result.redirect);
								return;
							}
							for (e in self.editors) {
								if (self.editors.hasOwnProperty(e)) {
									if (result.hasOwnProperty(e)) {
										newEditor = self.editors[e].setData(result[e]);
										if (newEditor) {
											self.editors[e] = newEditor;
										}
										successCount += 1;
									}
								}
							}
						}
						message = (successCount === taskCount) ? directTranslate("The page has been saved.") : directTranslate("Error. Not everything could be saved.") + " (" + successCount + "/" + taskCount + " " + directTranslate("saved.") + ")";
						self.notify(message);
						// if (options.success) { options.success.call(self, message); }
					},
					data: data
				});
			} else {
				message = directTranslate('Nothing to save.');
				this.notify(message);
				// if (options.success) { options.success.call(self, message); }
			}

		}
	};

	$.fn.directSaveButton = function (options) {
		if (options) {
			$.directEdit.fn.command = options.command || ['action', 'direct-save-page'];
		}
		this.click(function () {
			$.directEdit.fn.saveAll();
			return false;
		});

		// show notify message on page load when specified as ?de_message=saved
		// todo does not validate js Lint
		function getQueryParams(qs) {
			var params = {}, tokens, re = /[?&]?([^=]+)=([^&]*)/g;

			qs = qs.split("+").join(" ");
			while (tokens = re.exec(qs)) {
				params[decodeURIComponent(tokens[1])] = decodeURIComponent(tokens[2]);
			}
			return params;
		}

		var query = getQueryParams(document.location.search);
		if (query.de_message  === 'saved') {
			directNotify(directTranslate("The page has been saved."));
		}
	};

	// ping server every 10 minutes to keep the session alive
	setInterval(function () { $.post($.directEdit.fn.ajaxUrl); }, 600000);

	// prevent leaving the page when changes are made
	$(window).on('beforeunload', function () {
		if ($.directEdit.fn.taskCount() > 0) {
			return 'De page has been changed, do you really want to leave the page?';
		}
	});

	$.widget("directEdit.directPageOptions", {
		options: {},
		_create: function () {
			var openOptions, saveAll, self;
			self = this;
			openOptions = (function () {
				return function () {
					self.element.dialog({'title': 'Page options', 'modal': true, width: 800, dialogClass: 'direct-edit'});
					return false;
				};
			}());
			saveAll = (function () {
				return function () {
					self.modified = true;
					$.directEdit.fn.saveAll();
					return false;
				};
			}());
			$.fn.directOptionButton = function () {
				$(this).click(openOptions);
			};
			this.createCategoryEditor();
			this.element.find('input[type=submit]').click(saveAll);
			this.element.hide();
		},
		createCategoryEditor : function () {
			var categoryEditor, categoryInput, categorySelect, categories, category, categoryEditorList, addLink, editTitle, countNew, closeEditTitles, updateCategories, elementRemove, addCategory;

			updateCategories = function () {
				var element, selected, options;
				selected = categorySelect.val();
				options = '';
				
				categories = [];
				categoryEditorList.find('li').each(function () {
					var isSelected;
					isSelected = function (id) {
						if (selected === id) {
							return ' selected="selected"';
						} else {
							return '';
						}
					}
					element = {};
					element.id = $(this).attr('id');
					element.name = $(this).find('.title-input').val();
					options += '<option value="' + element.id + '"' + isSelected(element.id) + '>' + element.name + '</option>';
					categories.push(element);
				});
				categorySelect.html(options);
				categoryInput.val(JSON.stringify(categories));
			};
			closeEditTitles = function () {
				categoryEditorList.find('li').each(function () {
					var title, titleInput;
					title = $(this).find('.title');
					titleInput = $(this).find('.title-input');
					title.html(titleInput.val() || '<span style="color:grey;">New category</span>');
					title.show();
					titleInput.hide();
				});
				$('html').unbind("click");
				$(document).unbind("keypress");
				updateCategories();
			};
			editTitle = function (element) {
				return function () {
					var title, titleInput;
					title = element.find('.title').hide();
					titleInput = element.find('.title-input').show();
					titleInput.click(function (event) {event.stopPropagation(); });
					$('html').click(closeEditTitles);
					$(document).keypress(function (e) {
						if (e.which === 13) {
							closeEditTitles();
						}
					});
				};
			};
			elementRemove = function (element) {
				return function () {
					element.remove();
					closeEditTitles();
				};
			};
			addCategory = function (name, id) {
				var deleteLink, title, element;
				if (!id) {
					id = 'new-' + countNew;
					countNew += 1;
				}
				element = $('<li id="' + id + '"><input class="title-input somewidth" value="' + name + '" style="display:none;"></li>');
				title = $('<span class="title somewidth">' + name + '</span>');
				title.dblclick(editTitle(element));
				deleteLink = $('<a class="pointer">delete</a>');
				deleteLink.click(elementRemove(element));
				element.append(title).append(deleteLink);
				categoryEditorList.append(element);
			};

			// execution starts here
			categoryInput = $('#categoryInput');
			if (categoryInput && categoryInput.val()) {
				categories = jQuery.parseJSON(categoryInput.val());
				countNew = 0;
				categoryEditor = $('#categoryEditor');
				categorySelect = $(categoryInput[0].form).find('[name="de_category"]');
				categoryEditorList = $('<ul>').appendTo(categoryEditor);
				for (category = 0; category < categories.length; category += 1) {
					addCategory(categories[category].name, categories[category].id);
				}
				addLink = $('<a class="pointer add-new">add new</a>');
				addLink.click(function () {
					addCategory('', '');
					closeEditTitles();
				});
				$('<div>').append(addLink).insertAfter(categoryEditorList);
			}
		},
		isModified: function () {
			return (this.modified === true);
		},
		getData: function () {
			return this.element.find('form').serializeObject();
		},
		setData: function () {
			this.modified = false;
		}
	});


	$.fn.serializeObject = function () {
		var o = {}, a = this.serializeArray();
		jQuery.each(a, function () {
			if (o[this.name] !== undefined) {
				if (!o[this.name].push) {
					o[this.name] = [o[this.name]];
				}
				o[this.name].push(this.value || '');
			} else {
				o[this.name] = this.value || '';
			}
		});
		return o;
	};
	// define global functions
	directNotify = $.directEdit.fn.notify;
	directEdit = $.directEdit.fn.directEdit;
	directTranslate = function (m) {
		var r;
		if (typeof directTranslations === 'object') {
			r = directTranslations[m];
			if (!r) {
				console.log('not translated yet: ' + m);
			}
		}
		return r || m;
	};
}(jQuery));

