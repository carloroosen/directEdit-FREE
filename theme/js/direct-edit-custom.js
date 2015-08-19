/*
 *	a $ UI widget for rich text editting, part of the directEdit project
 *	(c) 2012 Carlo Roosen (http://www.carloroosen.nl)
 */

/*jslint nomen: true, browser: true */
/*global jQuery: false, console: false */

(function ($) {
	"use strict";
	$.directEdit.directTextEditor.prototype.buttonDefinitions = {
		b : {
			command: 'bold',
			tooltip: 'bold',
			icon: 'direct-icon-bold'
		},
		i : {
			command: 'italic',
			tooltip: 'italic',
			icon: 'direct-icon-italic'
		},
		'p' : {
			command: 'formatBlock',
			commandValue : 'P',
			tooltip: 'paragraph',
			icon: 'direct-icon-p',
		},
		'p-normal' : {
			// use in combination with p-lead, otherwise use 'p'
			icon: 'direct-icon-p',
			tooltip: 'normal paragraph',
			command : function () {
				if (document.queryCommandValue("formatBlock") && document.queryCommandValue("formatBlock").toUpperCase() !== 'P') {
					document.execCommand('formatBlock', false, 'P');
				}
				$(this.lastSelection.startContainer.parentNode).removeClass('lead');
			},
			queryState : function () {
				var state;
				state = document.queryCommandValue("formatBlock").toUpperCase() === 'P' && !$(window.getSelection().getRangeAt(0).startContainer.parentNode).hasClass('lead');
				return state;
			}
		},
		'p-lead' : {
			icon : 'direct-icon-p-lead',
			tooltip: 'introduction paragraph',
			command : function () {
				if (document.queryCommandValue("formatBlock") && document.queryCommandValue("formatBlock").toUpperCase() !== 'P') {
					document.execCommand('formatBlock', false, 'P');
				}
				$(this.lastSelection.startContainer.parentNode).addClass('lead');
			},
			queryState : function () {
				var state;
				state = document.queryCommandValue("formatBlock").toUpperCase() === 'P' && $(window.getSelection().getRangeAt(0).startContainer.parentNode).hasClass('lead');
				return state;
			}
		},
		h1 : {
			command: 'formatBlock',
			commandValue : 'H1',
			tooltip: 'header 1',
			icon: 'direct-icon-h1'
		},
		h2 : {
			command: 'formatBlock',
			commandValue : 'H2',
			tooltip: 'header 2',
			icon: 'direct-icon-h2'
		},
		h3 : {
			command: 'formatBlock',
			commandValue : 'H3',
			tooltip: 'header 3',
			icon: 'direct-icon-h3'
		},
		h4 : {
			command: 'formatBlock',
			commandValue : 'H4',
			tooltip: 'header 4',
			icon: 'direct-icon-h4'
		},
		ul : {
			command: 'insertUnorderedList',
			tooltip: 'bullet list',
			icon: 'direct-icon-list-ul'
		},
		ol : {
			command: 'insertOrderedList',
			tooltip: 'numbered list',
			icon: 'direct-icon-list-ol'
		},
		image : {
			icon : 'direct-icon-image',
			tooltip: 'insert image',
			createDialog: function () {
				var self = this, imageEditorOptions = this.options.buttonOptions.image;
				imageEditorOptions.callback = function (result) {
					self.eventHandlers.restoreSelection();
					document.execCommand('inserthtml', false, result.content);
				};
				this.element.directImageEditor(imageEditorOptions);
				return this.element.directImageEditor('getDialog');
			}
		},
		'link' : {
			icon : 'direct-icon-link',
			tooltip: 'insert link',
			queryState : false,
			createDialog: function (button) {
				var options = this.options.buttonOptions.link || {};
				options.textContainer = this.element;
				button.directLinkEditor(options);
				return button.directLinkEditor('getDialog');
			}
		},
		'link-button' : {
			icon : 'direct-icon-link-button',
			tooltip: 'insert link',
			queryState : false,
			createDialog: function (button) {
				var self = this, options = this.options.buttonOptions.link || {};
				options.textContainer = this.element;
				options.callback = function (linkNode) {
					var range, children;
					if (linkNode) {
						$(linkNode).wrap('<p class="button">');
						self._validateContent();
						self.lastSelection.selectNode(linkNode);
						self.eventHandlers.restoreSelection();
					} else {
						// remove 'button' class, works for most cases but is not foolproof
						range = window.getSelection().getRangeAt(0);
						if (range && range.startContainer.hasChildNodes()) {
							children = range.startContainer.childNodes;
							if (range.startOffset < children.length) {
								$(children[range.startOffset]).removeClass('button');
							}
						}
					}
				};
				button.directLinkEditor(options);
				return button.directLinkEditor('getDialog');
			}
		},
		'file' : {
			icon : 'direct-icon-file',
			tooltip: 'insert file',
			createDialog: function () {
				var self = this;
				// todo implement the functionality in directLinkEditor also in directFileUploader
				this.element.directFileUploader({
					'callback': function (result) {
						self.eventHandlers.restoreSelection();
						document.execCommand('inserthtml', false, '<p class="download"><a href="' + result.url + '">' + result.filename + '</a></p>');
					}
				});
				return this.element.directFileUploader('getDialog');
			}
		},
		'video' : {
			icon : 'direct-icon-video',
			tooltip: 'insert youtube video',
			queryState : false,
			createDialog: function () {
				var options, insertButton, dialog, dialogId, dialogSubmitCb, codeInput, self = this;
				options = {
					autoOpen: false,
					width: 540,
					height: 160,
					title: "Enter Youtube code (example WrrsQUwgm6s)",
					modal: false,
					resizable: false,
					draggable: false,
					dialogClass: 'richTextLinkDialog direct-edit'
				};
				dialog = $('<div id="' + dialogId + '"><form><input type="text" name="videocode" value="" /><input type="button" id="insertButton" value="Insert" /></form></div>');
				codeInput = $('input[name=videocode]', dialog);
				insertButton = $('input#insertButton', dialog);
				codeInput.focus(function () {this.select(); });
				dialogSubmitCb = function () {
					var code;
					code = codeInput.val();
					self.eventHandlers.restoreSelection();
					document.execCommand('inserthtml', false, '<div class="embed-container noValidate"><iframe src="http://www.youtube.com/embed/' + code + '?rel=0" allowfullscreen></iframe></div>');
					self.element.trigger('change');
					dialog.dialog('close');
					return false;
				};
				insertButton.click(dialogSubmitCb);
				dialog.dialog(options);
				dialog.bind('dialogopen', function () {
					var codeInput;
					codeInput = $('input[name=videocode]', dialog);
					if (self.lastSelection) {
						if (self.lastSelection.startContainer.parentNode.href === undefined) {
							codeInput.val(options.defaultUrl);
						} else {
							codeInput.val($(self.lastSelection.startContainer.parentNode).attr('href'));
							$(codeInput[0].form).find('input[type=submit]').val('update');
						}
					}
				});
				return dialog;
			}
		}
	};
}(jQuery));

/* other buttons
		left : {
			command: 'justifyLeft',
			tooltip: 'align left',
			icon: 'direct-icon-align-left'
		},
		center : {
			command: 'justifyCenter',
			tooltip: 'align center',
			icon: 'direct-icon-align-center'
		},
		right : {
			command: 'justifyRight',
			tooltip: 'align right',
			icon: 'direct-icon-align-right'
		},
		h1 : {
			command: 'formatBlock',
			commandValue : 'H1',
			tooltip: 'header 1',
			icon: 'direct-icon-h1'
		},
		h4 : {
			command: 'formatBlock',
			commandValue : 'H4',
			tooltip: 'header 4',
			icon: 'direct-icon-h4'
		},
		undo : {
			command: 'undo',
			tooltip: 'undo',
			queryState : false,
			icon : 'direct-icon-undo'
		},
		redo : {
			command: 'redo',
			tooltip: 'redo',
			queryState : false,
			icon : 'direct-icon-redo'
		},
		blockquote: {
			command: 'formatBlock',
			commandValue: 'blockquote',
			tooltip: 'quotation',
			icon: 'direct-icon-quote'
		},
		ul : {
			command: 'insertUnorderedList',
			tooltip: 'bullet list',
			icon: 'direct-icon-list-ul'
		},
		ol : {
			command: 'insertOrderedList',
			tooltip: 'numbered list',
			icon: 'direct-icon-list-ol'
		},
		'yellow' : {
			icon : 'direct-icon-color yellow',
			tooltip: 'yellow',
			queryState : false,
			command : function () {
				this.element
					.removeClass('yellow green-light green-dark grey grey-light blue white black')
					.addClass('yellow');
			}
		},
*/