/*jslint nomen: true, browser: true */
/*global jQuery: false, console: false */

(function ($) {
	"use strict";
	$.widget("directEdit.directLinkEditor", {
		additionalData: null,
		options: {
			buttonTextFollowLink: 'follow link',
			buttonTextEditLink: 'edit link',
			buttonTextDelete: 'delete',
			buttonTextShow: 'show',
			buttonTextHide: 'hide',
			buttonTextMoveUp: 'move up',
			buttonTextMoveDown: 'move down',
			deleteCommand : ['action', 'direct-delete-post'],
			hideCommand : ['action', 'direct-hide-post'],
			showCommand : ['action', 'direct-show-post']
		},
		_create: function () {
			var self, buttons;
			self = this;
			if (this.element.is('a')) {
				// standalone link
				this.originalLink = this.link = this.element.attr('href');
				this.element.addClass('direct-link-editor');
				this.element.removeAttr('href').addClass('direct-link-editor');

				buttons = $('<div class="direct-link-editor-buttons direct-edit">');
				if (this.options.buttonFollowLink === true) {
					this.follow = $('<a>' + this.options.buttonTextFollowLink + '</a>').attr('href', this.link).appendTo(buttons);
				}
				if (this.options.buttonEditLink === true) {
					$('<a>' + this.options.buttonTextEditLink + '</a>')
						.appendTo(buttons)
						.click(function () {return self.editLink(); });
				}
				if (this.options.buttonShow  === true || this.options.buttonHide  === true) {
					this.showButton = $('<a>' + this.options.buttonTextShow + '</a>').click(this.postCommand('show')).appendTo(buttons);
					this.hideButton = $('<a>' + this.options.buttonTextHide + '</a>').click(this.postCommand('hide')).appendTo(buttons);
					if (this.options.buttonHide  !== true) {
						this.hideButton.hide();
					} 
					if (this.options.buttonShow  !== true) {
						this.showButton.hide();
					}
				}
				if (this.options.buttonDelete === true) {
					$('<a>' + this.options.buttonTextDelete + '</a>').click(this.postCommand('delete')).appendTo(buttons);
				}

				if (this.options.buttonSort === true ) {
					this.orderCount = this.element.attr('data-count');
					this.moveUp = $('<a>' + this.options.buttonTextMoveUp + '</a>').click(function() {var elem = self.element; var prev = elem.prev(); var count = self.options.orderCount; elem.insertBefore(prev); self.adjustOrderParams(count - 1); prev.data('directEdit-directLinkEditor').adjustOrderParams(count);}).appendTo(buttons);
					this.moveDown = $('<a>' + this.options.buttonTextMoveDown + '</a>').click(function() {var elem = self.element; var next = elem.next(); var count = self.options.orderCount; elem.insertAfter(next); self.adjustOrderParams(count + 1); next.data('directEdit-directLinkEditor').adjustOrderParams(count);}).appendTo(buttons);
					this.adjustOrderParams();
				}
				
				this.element.prepend(buttons);
			}
			this.createDialog();
		},
		adjustOrderParams : function (newOrderCount) {
			if (newOrderCount) {
				this.options.orderCount = newOrderCount;
			}
			
			if (this.element.prev().is('a')) {
				this.moveUp.show();
			} else {
				this.moveUp.hide();
			}
			if (this.element.next().is('a')) {
				this.moveDown.show();
			} else {
				this.moveDown.hide();
			}
		},
		postCommand : function (command) {
			var self = this;
			return function () {
				var result = {}, commandOption;
				if (command === 'delete') {
					if(!confirm('Do you really wish to delete this post?')) return;
				}
				result.data = self.additionalData;
				commandOption = self.options[command + 'Command'];
				result[commandOption[0]] = commandOption[1];
				$.ajax({
					url: self.options.ajaxUrl || $.directEdit.fn.ajaxUrl,
					type: 'POST',
					error: function () {
						window.alert('Cannot perform action');
					},
					dataType: 'json',
					success: function (data) {self.updateUI(data)},
					data: result
				});
			};
		},
		followLink : function () {
			window.location.href = this.link;
		},
		editLink : function () {
			if (this.options.notEditable !== true) {
				this.dialog.dialog('open');
				return false;
			}
		},
		updateUI : function(data) {
			if (data.action === 'addclass') {
				this.showButton.show();
				this.hideButton.hide();
				this.element.addClass(data.cssClass);
			} else if (data.action === 'removeclass') {
				this.hideButton.show();
				this.showButton.hide();
				this.element.removeClass(data.cssClass);
			} else if (data.action === 'delete') {
				this.element.hide();
			}
		},
		getLinkInRange : function (range) {
			var startNode, endNode; 
			startNode = range.startContainer.parentNode;
			endNode = range.endContainer.parentNode;
			return (this.getLinkFromNode(startNode) || this.getLinkFromNode(endNode));
		},
		getLinkFromNode : function (node) {
			var nodeInChain = node, linkNode;
			if (node &&  nodeInChain !== this.options.textContainer[0]) {
				do {
					if (nodeInChain.tagName.toLowerCase() === 'a') {
						linkNode = nodeInChain;
						break;
					}
					nodeInChain = nodeInChain.parentNode;
				} while(nodeInChain !== this.options.textContainer[0])
			}
			return linkNode;
		},
		createDialog: function () {
			var dialogOptions, dialog, saveLink, urlInput, saveLinkButton, removeLinkButton, p, prefix, prefixesHTML,
				changePrefix, changePrefixCallback, createLinkInSelection, setSingleRange, currentRange, self = this;
			var data = {};

			dialogOptions = {
				autoOpen: false,
				width: 540,
				title: "Edit Link",
				modal: false,
				resizable: false,
				draggable: false,
				dialogClass: 'direct-edit'
			};
			if (this.options.textContainer) {
				// link in rich text
				createLinkInSelection = function (range, href, attributes) {
					var linkNode, selectedNode = null;
					if (typeof (range) !== 'object') {
						console.log('no range found');
						return;
					}
					selectedNode = range.startContainer.childNodes[range.startOffset];
					linkNode = self.getLinkInRange(range);
					if (href) {
						if (!linkNode) {
							// create link
							setSingleRange(range);
							document.execCommand('createlink', null, href);
							linkNode = self.getLinkInRange(window.getSelection().getRangeAt(0)) || self.getLinkFromNode(selectedNode);
						} else {
							// modify link
							linkNode.href = href;
						}
						if (linkNode && attributes) {
							$(linkNode).attr(attributes);
							if (attributes.target == '') {
								$(linkNode).removeAttr('target');
							}
						}
					} else {
						// remove link
						if (linkNode) {
							range.selectNode(linkNode);
						}
						setSingleRange(range);
						document.execCommand('unlink', null, '');
						linkNode = null;
					}
					range.collapse(true);
					setSingleRange(range);
					return $(linkNode);
				};
				setSingleRange = function (range) {
					var selection = window.getSelection();
					selection.removeAllRanges();
					selection.addRange(range);
				};
			}
			changePrefixCallback = function (prefix) {
				return function () {
					changePrefix(prefix);
				};
			};
			changePrefix = function (prefix) {
				self.prefix = prefix;
				if (prefix === '/' && window.location.origin) {
					$('span#prefix', dialog).html(window.location.origin + prefix);
				} else {
					$('span#prefix', dialog).html(prefix);
				}
			};
			saveLink = function (remove) {
				return function () {
					var linkNode, attributes;
					if (remove) {
						self.link = '';
					} else {
						self.link = self.prefix + urlInput.val();
						if (self.prefix == '') {
							if (self.link.indexOf('http://') === -1 && self.link.indexOf('https://') === -1) {
								self.link = 'http://' + self.link;
							}
						}
					}
					if (self.follow) { self.follow.attr('href', self.link); }
					attributes = self.options.attributes || {};
					attributes.target = (self.prefix === 'http://' || self.prefix === 'https://' || self.prefix == '') ? '_blank' : '';
					if (self.options.textContainer) {
						linkNode = createLinkInSelection(currentRange, self.link, attributes);
					}
					if (self.options.callback && typeof (self.options.callback) === 'function') {
						self.options.callback.call(self, linkNode);
					}
					dialog.dialog('close');
					return false;
				};
			};
			dialog = $('<div><form class="linkForm"><br><span id="prefix">/</span><input class="url" type="text" name="url" /><input type="button" id="saveLinkButton" value="Save" /></form></div>');
			dialog.find('span#prefix').before('<div id="internalLinksDiv">internal links: <select id="internalLinks"><option value="">&nbsp;</option></select><br><br></div>');
			if (this.options.textContainer) {
				dialog.find('form.linkForm').append('<input type="button" id="removeLinkButton" value="Remove" />');
			}
			if (this.options.prefixes) {
				prefixesHTML = $('<div>').prependTo($('form', dialog));
				for (p in this.options.prefixes) {
					if (this.options.prefixes.hasOwnProperty(p)) {
						prefix = $('<label><input type="radio" name="prefix" value="' + this.options.prefixes[p][0] + '">' + this.options.prefixes[p][1] + '</label> ').change(changePrefixCallback(this.options.prefixes[p][0])).appendTo(prefixesHTML);
					}
				}
			}
			urlInput = $('input[name=url]', dialog);
			saveLinkButton = $('input#saveLinkButton', dialog);
			removeLinkButton = $('input#removeLinkButton', dialog);
			urlInput.focus(function () {this.select(); });
			saveLinkButton.click(saveLink(false));
			removeLinkButton.click(saveLink(true));
			dialog.dialog(dialogOptions);
			dialog.bind('dialogopen', function () {
				var p, plainLink, linkNode;
				currentRange = window.getSelection().getRangeAt(0);
				if (self.options.textContainer && currentRange) {
					linkNode = self.getLinkInRange(currentRange) || self.getLinkFromNode(currentRange.startContainer.childNodes[currentRange.startOffset]);
					self.link = linkNode ? $(linkNode).attr('href') : '';
				}
				if (self.options.prefixes) {
					$('input:radio[value="' + self.options.prefixes[0][0] + '"]', dialog).prop('checked', true);
					if (self.options.prefixes[0][0] == '/') {
						$('div#internalLinksDiv', dialog).show();
					} else {
						$('div#internalLinksDiv', dialog).hide();
					}
				}
				// get default prefix
				changePrefix('/');
				plainLink = (self.link && self.link.indexOf('/') === 0) ? self.link.substring(1) : self.link;
				var prefixDefined = false;
				var prefixEmpty = false;
				if (self.link && self.options.prefixes) {
					for (p in self.options.prefixes) {
						if (self.options.prefixes[p][0]) {
							if (self.options.prefixes.hasOwnProperty(p) && self.link && self.link.indexOf(self.options.prefixes[p][0]) === 0) {
								$('input:radio[value="' + self.options.prefixes[p][0] + '"]', dialog).prop('checked', true);
								if (self.options.prefixes[p][0] == '/') {
									$('div#internalLinksDiv', dialog).show();
								} else {
									$('div#internalLinksDiv', dialog).hide();
								}
								changePrefix(self.options.prefixes[p][0]);
								plainLink = self.link.substring(self.prefix.length);
								prefixDefined = true;
							}
						} else {
							prefixEmpty = true;
						}
					}
					
					if (! prefixDefined && prefixEmpty) {
						$('input:radio[value=""]', dialog).prop('checked', true);
						$('div#internalLinksDiv', dialog).hide();
						changePrefix('');
					}
				}
				$('input[name=url]', dialog).val(plainLink);
				$('select#internalLinks option[value="'+plainLink+'"]', dialog).prop('selected', true);
			});
			$('input:radio[name="prefix"]', dialog).bind('change', function () {
				if ($(this).val() == '/') {
					$('div#internalLinksDiv', dialog).show();
					$('select#internalLinks option[value="'+$('input[name="url"]', dialog).val()+'"]', dialog).prop('selected', true);
				} else {
					$('div#internalLinksDiv', dialog).hide();
				}
			});
			$('select#internalLinks', dialog).bind('change', function () {
				$('input[name="url"]', dialog).val($(this).val());
			});

			// The list of internal links
			var result = $.directEdit.fn.internalLinks;
			if (typeof (result) === 'object') {
				//console.log(result);
				for(var index in result) { 
					//console.log(result[index]);
					dialog.find('select#internalLinks').append('<option value="' + result[index] + '">' + index + '</option');
				}
			}

			this.dialog = dialog;
		},
		getDialog: function () {
			return this.dialog;
		},
		isModified: function () {
			return (this.originalLink !== this.link || this.options.orderCount != this.orderCount);
		},
		getData: function () {
			var result = {};
			if (this.originalLink !== this.link) {
				result.link = this.link;
			}
			if (this.options.buttonSort && this.options.orderCount != this.orderCount) {
				result.index = this.options.orderIndex;
				result.count = this.options.orderCount;
			}
			result.data = this.additionalData;
			return result;
		},
		setData: function (result) {
			if (result.url !== undefined) {
				this.link = result.url;
			}
			if (result.count !== undefined) {
				this.orderCount = result.count;
			}
			$.extend(this.additionalData, result.data);
			this.originalLink = this.link;
		},
		setAdditionalData: function (data) {
			this.additionalData = data;
		}
	});
}(jQuery));
