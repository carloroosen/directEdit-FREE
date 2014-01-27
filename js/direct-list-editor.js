/*jslint nomen: true, browser: true */
/*global jQuery: false, console: false, directEdit : false, directNotify : false */
(function ($) {
	"use strict";
	$.widget("directEdit.directListEditor", {
		additionalData : null,
		ajaxUrl : '',
		options : {
			ajaxUrl : '',
			init : null,
			listSelector : '',
			placeholder : '<a>add first item</a>',
			callback : null,
			command : ['action', 'direct-']  // name and value prefix for POST 
		},
		_create : function () {
			if (this.element.attr('data-definition')) {
				this.definition = JSON.parse(this.element.attr('data-definition'));
			}
			this.items = {};
		},
		_init: function () {
			var self, length, allItems, showcommand, getCustomCommand;

			self = this;
			this.element.find('.direct-list-editor-buttons').remove();
			if ((self.options.listSelector && this.element.is(self.options.listSelector)) || this.element.find(self.options.listSelector).length !== 1) {
				this.list = this.element;
			} else {
				this.list = this.element.find(self.options.listSelector);
			}
			allItems = this.list.children();
			length = $(allItems).length;

			showcommand = function (commandName, index) {
				var position;
				if (self.options.commands[commandName].showOn) {
					if (length === 1) {
						position = 'single';
					} else if (index === 0) {
						position = 'first';
					} else if (index === length - 1) {
						position = 'last';
					} else {
						position = 'middle';
					}
					if (self.options.commands[commandName].showOn.indexOf(position) === -1) {
						return false;
					}
				}
				return true;
			};
			getCustomCommand = function (action, element) {
				return function () {
					action(element);
				};
			};
			if (allItems.length) {
				$.each(allItems, function (index) {
					var commandName, commandHTML, divClass, divClasses, action, li, buttonWrapper;
					li = $(this);
					self.items[self.definition[index]] = li;
					li.addClass('direct-list-editor-item');
					/* for each element create a wrapper and optional child containers */
					divClasses = [];
					buttonWrapper = $('<div>').addClass('direct-list-editor-buttons direct-edit');
					if (self.options.divClasses) {
						for (divClass in (self.options.divClasses)) {
							if (self.options.divClasses.hasOwnProperty(divClass)) {
								divClasses[divClass] = $('<div class="' + self.options.divClasses[divClass] + '">');
								buttonWrapper.append(divClasses[divClass]);
							}
						}
					}
					for (commandName in self.options.commands) {
						if (self.options.commands.hasOwnProperty(commandName)) {
							if (showcommand(commandName, index)) {
								/* add commands html */
								commandHTML = $('<a>' + self.options.commands[commandName].caption + '</a>');
								divClass = self.options.commands[commandName].div;
								if (divClass && divClasses[divClass]) {
									divClasses[divClass].append(commandHTML);
								} else {
									console.log(divClass, divClasses[divClass]);
									buttonWrapper.append(commandHTML);
								}
								/* add event handlers */
								if (commandName.substring(0, 7) === 'custom-') {
									action = getCustomCommand(self.options.commands[commandName].action, li);
								} else {
									action = self._getCommand(commandName, index);
								}
								commandHTML.click(action);
							}
						}
					}
					$(this).prepend(buttonWrapper);
				});
			} else {
				// todo temp solution to add the first item
				$(this.options.placeholder)
					.appendTo(this.list)
					.click(self._getCommand('add', 0));
			}
			if (this.options.init) { this.options.init.call(this); }
		},
		_getCommand : function (commandString, index) {
			var self = this;
			return function () {
				var ajaxOptions;

				ajaxOptions = {
					url: self.options.ajaxUrl || $.directEdit.fn.ajaxUrl,
					type: 'POST',
					error: function () {
						directNotify(directTranslate('Could not save changes.'));
					},
					dataType: 'json',
					success: function (result) {
						self.setData(result, true);
					},
					data: {
						index : index,
						data : self.additionalData
					}
				};
				ajaxOptions.data[self.options.command[0]] = self.options.command[1] + commandString;

				$.ajax(ajaxOptions);
				if (self.dialog) { self.dialog.close(); }
			};
		},
		reload : function (index) {
			this._getCommand('reload', index)();
		},
		destroy: function () {
			$.Widget.prototype.destroy.call(this);
		},
		isModified: function () {
			return this.modified || false;
		},
		getData: function () {
			var data = {};
			data.data = this.additionalData;
			return data;
		},
		setData: function (returnData, modified) {
			var i, newItem, tempcontainer;
			this.modified = modified || false; // if falsy then false
			tempcontainer = $('<div id="tempcontainer">').appendTo('body');
			if (returnData) {
				if (returnData.definition) {
					this.definition = returnData.definition;
				}
				$.extend(this.additionalData, returnData.data);
				if (returnData.newItemContent && returnData.newItemIdentifier) {
					// return element can be wrapped or not, assume it is
					if ($(returnData.newItemContent).is(this.options.listSelector)) {
						newItem = $(returnData.newItemContent).children().last();
					} else {
						newItem = $(returnData.newItemContent).find(this.options.listSelector).children().last();
					}
					if (!newItem.length) {
						newItem = $(returnData.newItemContent);
					}
					// TODO preserve data format in dE options, now numbers become strings so the return param must be converted to string also
					this.items[returnData.newItemIdentifier] = newItem;
					tempcontainer.append(newItem);
					// init all editables inside newItem
					directEdit(newItem);
				}
				// move items to a safe place
				tempcontainer.append(this.list.contents());
				// move items back into the list in the right order
				for (i in this.definition) {
					if (this.definition.hasOwnProperty(i)) {
						// move the items from flexslider to newList (child of flexsliderNew)
						this.list.append(this.items[this.definition[i]]);
					}
				}
				// remove the garbage
				tempcontainer.remove();
				if (this.options.callback && typeof this.options.callback === 'function') {
					this.options.callback.call(this, returnData.activeItem);
				} else {
					this._init();
				}
			}
		},
		setAdditionalData: function (data) {
			this.additionalData = {};
			$.extend(this.additionalData, data);
		},
		setAjaxUrl: function (url) {
			this.ajaxUrl = url;
		}
	});
}(jQuery));
