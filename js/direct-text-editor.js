/*jslint nomen: true, browser: true */
/*global jQuery: false, console: false */

(function ($) {
	"use strict";
	$.widget("directEdit.directTextEditor", {
		toolbar: null,
		state: 'inactive',
		hasPlaceholderContent: false,
		eventHandlers : null,
		selection: null,
		originalValidatedContent: '',
		originalValidatedContentSet: false,
		additionalData: null,
		options: {
			debug: false,
			validate: false,
			instanceID: 0,
			format: 'block', // ["block","inline","plain","title"]
			placeholder: 'empty',
			formatRules : {
				'div' : 'h1,h2,h3,h4,p,blockquote,ol,ul',
				'p'   : 'b,i,a,br,img',
				'ul'  : 'li',
				'ol'  : 'li',
				'li'  : 'b,i,a,br',
				'blockquote' : 'b,i,a,br'
			}
		},
		_create: function () {
			if (this.options.format === 'plain' || this.options.format === 'title') {
				this._validateContent = this._validateContentPlain; // soo cool, just replace a function :)
			}

			// create member functions for eventHandlers that have a fixed reference to 'this' (closure). 
			this._createEventHandlers();

			// create toolbar and buttons
			if (this.options.buttons) { this._createToolbar(); }
			
			// Store original content to track changes
			this.originalContent = this.element.html();

			// configure browser editing
			this.tagName = this.element.get(0).tagName.toLowerCase();
			/*if (this.tagName !== 'div') {
				remove the tag and replaces it with a div, a workaround for browser bugs with editable h1,h2 etc tags
				unfortunately it does not work well, maybe make it optional?
				
				var copy = $('<div>')
					.html(this.element.html())
					.css({
						fontFamily: this.element.css('fontFamily'),
						fontSize: this.element.css('fontSize'),
						fontWeight: this.element.css('fontWeight'),
						fontStyle: this.element.css('fontStyle'),
						lineHeight: this.element.css('lineHeight'),
						marginTop: this.element.css('marginTop'),
						marginRight: this.element.css('marginRight'),
						marginBottom: this.element.css('marginBottom'),
						marginLeft: this.element.css('marginLeft'),
						paddingTop: this.element.css('paddingTop'),
						paddingRight: this.element.css('paddingRight'),
						paddingBottom: this.element.css('paddingBottom'),
						paddingLeft: this.element.css('paddingLeft'),
						display: this.element.css('display'),
						verticalAlign: this.element.css('verticalAlign'),
						textTransform: this.element.css('textTransform'),
						letterSpacing: this.element.css('letterSpacing'),
						backgroundColor: this.element.css('backgroundColor'),
						color: this.element.css('color')
					})
					.attr({
						'id':  this.element.attr('id'),
						'class': this.element.attr('class')
					})
					.insertAfter(this.element);
				this.element.remove();
				this.element = copy;
				
			}*/

			this.element.attr("contentEditable", true);
			document.execCommand('enableObjectResizing', false, 'false');
			this._styleWithCSS();
			// attach event handlers
			if (!this.enabled) {
				this.element.bind("focus", this.eventHandlers.focusOnText)
					.bind("blur", this.eventHandlers.blurOnText)
					.bind("keydown", this.eventHandlers.keyDown)
					.bind('mouseup', this.eventHandlers.storeSelection)
					.bind('change paste drop', this.eventHandlers.change) // delay them first
					.bind('changedelayed pastedelayed dropdelayed', this.eventHandlers.validateContent);
				this.enabled = true;
			}
			// todo this.toolbar.enable();
			this._setPlaceholder(true);
			return this;
		},
		_destroy: function () {
			this.toolbar.remove();
			this._setPlaceholder(false);
			this.element.unbind("focus", this.eventHandlers.focusOnText)
				.unbind("blur", this.eventHandlers.blurOnText)
				.unbind("keydown", this.eventHandlers.keyDown)
				.unbind('mouseup', this.eventHandlers.storeSelection)
				.unbind('change paste drop', this.eventHandlers.change)
				.unbind('changedelayed pastedelayed dropdelayed', this.eventHandlers.validateContent);
			this.element.attr("contentEditable", false);
			$.Widget.prototype.destroy.call(this);
		},
		activate: function () {
			$(this.element).addClass('inEditMode');
			this._validateContent();
			if (!this.originalValidatedContentSet) {
				// the text has not been changed by the user, maybe only by the validator or by the browser
				this.originalValidatedContent = this.element.html();
				this.originalValidatedContentSet = true;
			}
			if (this.toolbar) { this.toolbar.show(); }
			this._setPlaceholder(false);
			this._trigger("activated", this);
		},
		deactivate: function () {
			if (this.element[0].setSelectionRange) { this.element[0].setSelectionRange(0, 0); } // scrolls back long input fields
			$(this.element).removeClass('inEditMode');
			if (this.toolbar) { this.toolbar.hide(); }
			this._setPlaceholder(true);
			this._trigger("deactivated", this);
		},
		_setPlaceholder: function (show) {
			if (show) {
				if (/^((<br\s?\/?>|<p>|<\/p>|\s|&nbsp;)*)$/.test(this.element.html())) {
					this.element.html(this.options.placeholder);
					if (this.options.format === 'block') { this.element.contents().wrap('<p>'); }
					this.hasPlaceholderContent = true;
				}
			} else {
				if (this.hasPlaceholderContent) {
					this.hasPlaceholderContent = false;
					if (this.options.format === 'block') {
						var p = this.element.find('p').html('&nbsp;');
						var range = document.createRange();
						range.selectNode(p.get(0));
					} else {
						this.element.html('&nbsp;');
					}
				}
			}
		},
		isTouched :  function () {
			// is checked when saving
			return (this.originalContent !== this.element.html()) || this.isModified();
		},
		isModified: function () {
			// is checked when trying to leave the page, less strict than isTouched()
			var isModified;
			if (this.originalValidatedContentSet) {
				if (this.hasPlaceholderContent) {
					isModified = (this.originalValidatedContent !== '');
				} else {
					isModified = (this.originalValidatedContent !== this.element.html());
				}
			} else {
				isModified = false;
			}
			return isModified;
		},
		getData: function () {
			var result = {};
			this._validateContent();
			result.content = this.hasPlaceholderContent ? '' : this.element.html();
			result.data = this.additionalData;
			return result;
		},
		setData: function (result) {
			this.element.html(result.content);
			$.extend(this.additionalData, result.data);
			this.originalValidatedContent = this.originalContent = this.element.html();
			this.hasPlaceholderContent = false;
			this._setPlaceholder(true);
		},
		setAdditionalData: function (data) {
			this.additionalData = data;
		},
		_styleWithCSS: function () {
			try {
				return document.execCommand('styleWithCSS', 0, false);
			} catch (e) {
				try {
					return document.execCommand('useCSS', 0, true);
				} catch (e1) {
					try {
						return document.execCommand('styleWithCSS', false, false);
					} catch (e2) {
					}
				}
			}
		},
		// function factories
		_createEventHandlers : function () {
			var self = this;
			this.eventHandlers = {
				focusOnText : function () {
					switch (self.state) {
					case 'inactive':
						self.activate();
						self.state = 'active';
						if (self.options.debug) { console.log('focusOnText: inactive -> active'); }
						break;
					case 'active':
						// handler without side effect. Set state = 'active' before programmatically changing the focus to editor.
						break;
					case 'pluginHasFocus':
						if (self.options.debug) { console.log('focusOnText: pluginHasFocus -> active'); }
						self.state = 'active';
						break;
					default:
						if (self.options.debug) { console.log('Unforeseen situation focusOnText while ' + self.state); }
					}
				},
				blurOnText : function () {
					switch (self.state) {
					case 'active':
						self.deactivate();
						self.state = 'inactive';
						if (self.options.debug) { console.log('blurOnText: active -> inactive'); }
						break;
					case 'mouseActiveOnToolbar':
					case 'pluginHasFocus':
						if (self.options.debug) { console.log('blurOnText: ' + self.state + ' -> ' + self.state); }
						break;
					default:
						if (self.options.debug) { console.log('Unforeseen situation blurOnText while ' + self.state); }
					}
				},
				toolbarMouseDown : function (event) {
					switch (self.state) {
					case 'active':
						event.preventDefault();
						if (self.options.debug) { console.log('preventDefault mouseDown on Toolbar'); }
						break;
					case 'pluginHasFocus':
						// do nothing
						break;
					default:
						if (self.options.debug) { console.log('Unforeseen situation toolbarMouseDown while ' + self.state); }
					}
				},
				toolbarMouseUp : function () {
					switch (self.state) {
					case 'active':
						self.state = 'mouseActiveOnToolbar';
						if (self.options.debug) { console.log('toolbarMouseUp: active -> mouseActiveOnToolbar'); }
						setTimeout(self.eventHandlers.toolbarMouseReset, 100);
						break;
					case 'pluginHasFocus':
						self.state = 'toolbarWhilePluginActive';
						if (self.options.debug) { console.log('toolbarMouseUp: pluginHasFocus -> toolbarWhilePluginActive'); }
						break;
					default:
						if (self.options.debug) { console.log('Unforeseen situation toolbarMouseUp while ' + self.state); }
					}
				},
				toolbarMouseReset : function () {
					switch (self.state) {
					case 'mouseActiveOnToolbar':
						self.state = 'active';
						if (self.options.debug) { console.log('toolbarMouseReset: mouseActiveOnToolbar -> active'); }
						break;
					case 'pluginHasFocus':
					case 'active':
						// do nothing
						break;
					default:
						if (self.options.debug) { console.log('Unforeseen situation toolbarMouseReset while ' + self.state); }
					}
				},
				pluginTakesFocus : function () {
					switch (self.state) {
					case 'toolbarWhilePluginActive':
						if (self.options.debug) { console.log('pluginTakesFocus: ' + self.state + ' -> pluginHasFocus'); }
						// do nothing
						break;
					default:
						if (self.options.debug) { console.log('pluginTakesFocus: ' + self.state + ' -> pluginHasFocus'); }
						self.eventHandlers.restoreSelection();
						self.state = 'pluginHasFocus';
					}
				},
				pluginReturnsFocus : function () {
					switch (self.state) {
					case 'pluginHasFocus':
						self.state = 'active';
						self.element.focus();
						if (self.options.debug) { console.log('pluginReturnsFocus: pluginHasFocus -> active'); }
						break;
					case 'active':
					case 'inactive':
						// do nothing
						break;
					case 'toolbarWhilePluginActive':
						self.state = 'mouseActiveOnToolbar';
						if (self.options.debug) { console.log('pluginReturnsFocus: toolbarWhilePluginActive -> mouseActiveOnToolbar'); }
						break;
					default:
						if (self.options.debug) { console.log('Unforeseen situation pluginReturnsFocus while ' + self.state); }
					}
				},
				pluginDialogBlur : function () {
					switch (self.state) {
					case 'active':
						// do nothing
						break;
					case 'toolbarWhilePluginActive':
						self.state = 'active';
						// TODO this does not work yet when button with dialog is clicked
						self.element.focus();
						if (self.options.debug) { console.log('pluginDialogBlur: toolbarWhilePluginActive -> active'); }
						break;
					case 'pluginHasFocus':
						// close all
						self.deactivate();
						self.state = 'inactive';
						if (self.options.debug) { console.log('pluginDialogBlur: pluginHasFocus -> inactive'); }
						break;
					default:
						if (self.options.debug) { console.log('Unforeseen situation pluginDialogBlur while ' + self.state); }
					}
				},
				keyDown : function (event) {
					switch (self.state) {
					case 'pluginHasFocus':
						// do nothing
						break;
					default:
						if (event.keyCode === 13) {
							// self.options.format can be 'block', 'inline', 'plain' or 'title'
							if (self.options.format !== 'block') {
								// no not generate a paragraph
								event.preventDefault();
								// insert a <br> instead
								if (self.options.format !== 'title') {
									document.execCommand("inserthtml", false, "<br/>#"); // needs an extra character to work, at least in FF
									document.execCommand("delete", false); // delete the extra character
								}
							}
						} else if (event.keyCode === 27) {
							if (self.options.debug) { console.log('escape pressed'); }
							// blur will invoke deactivation
							self.element.blur();
						}
					}
				},
				change : function (event) {
					var customevent = event.type + 'delayed';
					window.setTimeout(function () {	self.element.trigger(customevent); }, 10);
				},
				validateContent : function (event) {
					if (event.type === 'keyup' && event.keyCode !== 13) {
						return;
					}
					self._validateContent();
				},
				storeSelection: function (event) {
					var range;

					if ($(event.target).is('img')) {
						range = document.createRange();
						range.selectNode(event.target);
						window.getSelection().removeAllRanges();
						window.getSelection().addRange(range);
					}
					if (window.getSelection().rangeCount) {
						self.lastSelection = window.getSelection().getRangeAt(0);
					}
				},
				restoreSelection: function () {
					var selection = window.getSelection();
					selection.removeAllRanges();
					selection.addRange(self.lastSelection);
				}
			};
		},
		// content
		_validateContent : function (node, rule) {
			var self = this, plaintext = '', element, contents;
			
			if (!this.options.validate) return;
			
			if (!node) {
				node = this.element;
				rule = this.options.format === 'block' ? 'div' : 'p';
			}

			// escape from recursive loop, cleanup all markup in this node
			if (rule === 'plaintext') {
				plaintext = node.text().replace(/\s+/g, " ");
				if (node.html() !== plaintext) {  // don't interfere with html if not needed
					node.html(plaintext);
				}
				return;
			}
			//FF fix, remove <br> at the end of a <p>
			if (node.is('p')) {
				contents = node.contents();
				element = contents[contents.length - 1];
				if (element && element.tagName && element.tagName.toLowerCase() == 'br') {
					element.parentNode.removeChild(element);;
				}
			}

			// inline styling is allowed for images only
			if (node.prop("nodeName").toLowerCase() !== 'img') {
				node.removeAttr('style');
			}
			// recursively search the DOM tree
			node.contents().each(function () {
				var childNodeDOM, childNode, newRuleDefault, tagName, newRule;
				childNodeDOM = this;
				childNode = $(this);
				if (childNode.hasClass('noValidate')) { return; }
				if (childNodeDOM.nodeType !== 3 && childNode.is(self.options.formatRules[rule])) {
					// the tag itself is allowed, now see what rule should be used inside the tag
					// as a default a rule will be inherited, except for the rule 'top' child nodes will get 'plaintext' as a default rule
					newRuleDefault = (rule === self.tagName && rule !== 'p') ? 'plaintext' : rule;
					tagName = childNodeDOM.tagName.toLowerCase();
					// use a rule if defined, or use default
					newRule = (self.options.formatRules[tagName]) ? tagName : newRuleDefault;
					self._validateContent(childNode, newRule);
				} else {
					// the node is not allowed or contains plain text
					if (rule === self.tagName && rule !== 'p') {
						if (childNode.text().trim() === '' && !(childNode.is('img'))) {
							// it can be an entire tree of nodes, but it contains whitespace only
							childNode.remove();
						} else {
							// wrap it in a p and validate again
							childNode.wrap('<p />');
							self._validateContent(childNode.parent(), 'p');
						}
					} else {
						if (childNodeDOM.nodeType !== 3) {
							// if accepted in top, move upward (FF automatically places <ul> and <ol> outside a <p>, but Chrome needs some help here) 
							if (node.get(0).tagName.toLowerCase() === 'p' && childNode.is(self.options.formatRules[self.tagName])) {
								/* TODO, make it work :D
								contents = node.contents();
								// wrap the items before and after this childNode in a p and delete the parent p in which all are wrapped
								contents.slice(0, contents.index(childNode)).wrapAll('<p />');
								contents.slice(contents.index(childNode) + 1).wrapAll('<p />');
								self._validateContent(childNode, 'p');
								childNode.unwrap();*/
							} else {
								if (childNode.text().trim() === '') {
									childNode.remove();
								} else {
									// delete the node but keep its content
									self._validateContent(childNode, rule);
									childNode.contents().unwrap();
								}
							}
						} else {
							if (rule === 'ul' || rule === 'ol') {
								childNode.wrap('<li />');
							}
							// todo, generalize for all nodetypes that cannot contain text directly
							// else: all other plaintext is accepted
						}
					}
				}
			});
			// remove empty p's
			// find images in own p, and move them to the beginning of the next empty p.
			if(node === this.element && !node.is('p')) {
				var pars = this.element.find('p');
				if (pars.length > 1) {
					$(pars.get().reverse()).each(function () {
						var par = $(this), img;

						if (par.contents().length === 0) {
							par.remove();
						}
						if (par.contents().length === 1 && par.children('img').length == 1 && par.next().is('p')) {
							img = par.children('img');
							console.log('image moved to start of paragraph');
							par.next().prepend(img);
							par.remove();
						}
					});
				}
			}
		},
		_validateContentPlain: function () {
			var contentOrgStripped, contentNewStripped, getText, self = this;

			getText = function (node) {
				var res = '', br = '';
				node.contents().each(function () {
					res += br;
					br = '';
					if (this.nodeType !== 3) {
						if (this.tagName.toLowerCase() === 'br') {
							br = (self.options.format === 'title') ? ' ' : '<br>';
						} else {
							res += getText($(this));
						}
					} else {
						res += this.textContent;
					}
				});
				return res;
			};
			contentNewStripped = getText(this.element).replace(/\s{2,}/g, ' ');
			contentOrgStripped = this.element.html().replace(/\&nbsp;/g, ' ').replace(/\s{2,}/g, ' ');
			if (contentOrgStripped !== contentNewStripped) {
				this.element.html(contentNewStripped);
				window.getSelection().removeAllRanges();
			}
		},
		_createButton: function (buttonId) {
			var button, buttonOptions, buttonHolder, id, setButtonInactive, setButtonActive, uncheckButton, fireSecondClick,
				dialog, commandHandler, queryState, buttonDefinition;

			// create DOM
			if (!this.buttonDefinitions.hasOwnProperty(buttonId)) { return ''; }
			buttonDefinition = this.buttonDefinitions[buttonId];
			id = String('directEdit-textEditor-button-' + this.options.instanceID + '-' + buttonId);
			buttonHolder = $('<span><label for="' + id + '" title="' + buttonDefinition.tooltip + '">' + buttonDefinition.buttontext + '</label></span>');
			button = $('<input id="' + id + '" type="checkbox" />').appendTo(buttonHolder);
			buttonOptions = buttonDefinition.buttonOptions || {};
			// shorthand for icon
			if (buttonDefinition.icon) {
				buttonOptions.icons = {
					'primary': buttonDefinition.icon
				};
				buttonOptions.text = null;
			}
			if($.fn.button.noConflict) {
				$.fn.btn = $.fn.button.noConflict();
			}
			button.button(buttonOptions);
			// closures for event handlers
			(function (self, _button) {
				setButtonInactive = function () {
					_button.next('label').removeClass('ui-state-active');
					$(document).unbind('mouseup', setButtonInactive);
				};
				setButtonActive = function () {
					_button.next('label').addClass('ui-state-active');
					$(document).bind('mouseup', setButtonInactive);
				};
				uncheckButton = function () {
					_button.attr("checked", false);
					_button.button("refresh");
				};
				fireSecondClick = function () {
					_button.prev('label').click();
				};
				// command
				if (typeof buttonDefinition.createDialog === 'function') {
					dialog = buttonDefinition.createDialog.call(self, button);
					commandHandler = function () {
						dialog.dialog('open');
					};
					self._registerDialog(dialog);
				} else if (typeof buttonDefinition.command === 'function') {
					commandHandler = function () {
						self.eventHandlers.pluginTakesFocus();
						buttonDefinition.command.call(self, _button);
						self.eventHandlers.pluginReturnsFocus();
						self.element.trigger("change");
					};
				} else if (typeof buttonDefinition.command === 'string') {
					commandHandler = function () {
						self.eventHandlers.pluginTakesFocus();
						try {
							if (buttonDefinition.command === 'formatBlock') {
								// outdent will delete blockquote
								document.execCommand('outdent', false, '');
							}
							document.execCommand(buttonDefinition.command, false, buttonDefinition.commandValue);
						} catch (e) {}
						self.eventHandlers.pluginReturnsFocus();
						self.element.trigger("change");
					};
				}
				// querystate
				if (typeof buttonDefinition.queryState === 'function') {
					queryState = function () {
						_button.attr("checked", buttonDefinition.queryState.call(self));
						_button.button("refresh");
					};
				} else if (buttonDefinition.queryState !== false && typeof buttonDefinition.command === 'string') {
					queryState = function () {
						var buttonState;
						try {
							if (buttonDefinition.command === 'formatBlock') {
								buttonState = (document.queryCommandValue("formatBlock").toUpperCase() === buttonDefinition.commandValue);
							} else {
								buttonState = document.queryCommandState(buttonDefinition.command);
							}
							_button.attr("checked", buttonState);
							_button.button("refresh");
						} catch (e) {}
					};
				} else {
					queryState = null;
				}
			}(this, button));

			// bind event handlers
			button.prev('label').dblclick(fireSecondClick);
			button.prev('label').mousedown(this.eventHandlers.storeSelection);
			// action when button pressed
			button.bind('change', commandHandler);
			// manage button highlighting
			if (queryState) {
				this.element.bind('keyup paste change mouseup', queryState);
			} else {
				// only highlight after mousedown, dont allow button to stay checked
				button.next('label').bind('mousedown', setButtonActive);
				button.change(uncheckButton);
			}
			return buttonHolder;
		},
		_createToolbar: function () {
			var toolbar, toolbarContainer, createButtonset;
			toolbarContainer = $('#direct-text-editor-toolbar-container');
			// only one global instance 
			if (toolbarContainer.length === 0) {
				// create toolbarContainer
				toolbarContainer = $('<div id="direct-text-editor-toolbar-container" class="direct-edit">');
				toolbarContainer.css('position', 'fixed');
				toolbarContainer.draggable({cancel : 'label', scroll : false});
				$('body').append(toolbarContainer);
			}
			this.toolbar = toolbar = $('<div class="toolbar"><div class="grip"></div></div>').hide();
			toolbarContainer.append(toolbar);
			toolbar.bind('mousedown', this.eventHandlers.toolbarMouseDown);
			toolbar.bind('mouseup', this.eventHandlers.toolbarMouseUp);

			createButtonset = function (buttonElements) {
				var element, wrap, set;
				if (typeof buttonElements === 'string') {
					return this._createButton(buttonElements);
				}
				wrap = true;
				set = $();
				for (element in buttonElements) {
					if (buttonElements.hasOwnProperty(element)) {
						element = buttonElements[element];
						if (typeof element === 'object') { wrap = false; }
						set = set.add(createButtonset.call(this, element));
					}
				}
				if (wrap) {
					set = $('<span></span>').append(set).buttonset();
				}
				return set;
			};
			toolbar.append(createButtonset.call(this, this.options.buttons));
		},
		_registerDialog : function (dialog) {
			var self, setDialogBlurHandler;
			self = this;
			setDialogBlurHandler = (function (_self, _dialog) {
				return function (event) {
					// emulate a blur event on a dialog
					if (!$.contains(_dialog.dialog('widget')[0], event.target)) {
						// other event handlers might have changed the state already before this blur event
						_self.eventHandlers.pluginTakesFocus();
						if (self.options.debug) { console.log('pseudo blur on dialog, close dialog'); }
						_dialog.dialog('close');
					}
				};
			}(this, dialog));
			dialog.dialog("option", "focus", function () {
				setTimeout(function () { $(document).bind('click', setDialogBlurHandler); }, 20);
				self.eventHandlers.pluginTakesFocus();
			});
			dialog.dialog("option", "close", function () {
				$(document).unbind('click', setDialogBlurHandler);
				self.eventHandlers.pluginReturnsFocus();
			});
		}
	});
}(jQuery));


