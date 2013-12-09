/*jslint nomen: true, browser: true */
/*global jQuery: false, console: false */

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
		directEdit: function (arg1) {
			var self = $.directEdit.fn;
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
							link.directLinkEditor({'buttonFollowLink':true});
						} 
						console.log(thisOptions);
						if (thisOptions.unwrap) {
							parentElement = element.parent();
							parentElement.html(element.html());
							element = parentElement;
						}
						element.directTextEditor(thisOptions);
						editor = element.data("directEdit-directTextEditor");
						break;
					case 'file':
						element.directFileUploader(thisOptions);
						editor = element.data("directEdit-directFileUploader");
						break;
					case 'image':
						element.directImageEditor(thisOptions);
						editor = element.data("directEdit-directImageEditor");
						break;
					case 'link':
						element.directLinkEditor(thisOptions);
						editor = element.data("directEdit-directLinkEditor");
						break;
					case 'list':
						element.directListEditor(thisOptions);
						editor = element.data("directEdit-directListEditor");
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
					if (editor.isModified() || editor.options.alwaysSave) {
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
			$.directEdit.fn.ajaxUrl = options.ajaxUrl || '';
			$.directEdit.fn.command = options.command || ['action', 'direct-save-page'];
		}
		this.click(function () {
			$.directEdit.fn.saveAll();
			return false;
		});
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
			this.element.find('input[type=submit]').click(saveAll);
			this.element.hide();
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
		"use strict";
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
		if (typeof directTranslations === 'object')  {
			r = directTranslations[m];
			if (!r) console.log ('not translated yet: ' + m);
		}
		return r || m;
	};
}(jQuery));

