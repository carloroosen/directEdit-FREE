/*jslint nomen: true, browser: true */
/*global jQuery: false, console: false */

(function ($) {
	"use strict";
	$.widget("directEdit.directPostwrapperEditor", {
		additionalData: null,
		options: {
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

			this.element.addClass('direct-postwrapper-editor');

			buttons = $('<div class="direct-postwrapper-editor-buttons direct-edit">');
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
				this.moveUp = $('<a>' + this.options.buttonTextMoveUp + '</a>').click(function() {var elem = self.element; var prev = elem.prev(); var count = self.options.orderCount; elem.insertBefore(prev); self.adjustOrderParams(count - 1); prev.data('directEdit-directPostwrapperEditor').adjustOrderParams(count);}).appendTo(buttons);
				this.moveDown = $('<a>' + this.options.buttonTextMoveDown + '</a>').click(function() {var elem = self.element; var next = elem.next(); var count = self.options.orderCount; elem.insertAfter(next); self.adjustOrderParams(count + 1); next.data('directEdit-directPostwrapperEditor').adjustOrderParams(count);}).appendTo(buttons);
				this.adjustOrderParams();
			}
				
			this.element.prepend(buttons);
		},
		adjustOrderParams : function (newOrderCount) {
			if (newOrderCount) {
				this.options.orderCount = newOrderCount;
			}
			
			if (this.element.prev().is(this.element.prop('tagName'))) {
				this.moveUp.show();
			} else {
				this.moveUp.hide();
			}
			if (this.element.next().is(this.element.prop('tagName'))) {
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
		isModified: function () {
			return (this.options.orderCount != this.orderCount);
		},
		getData: function () {
			var result = {};
			if (this.options.buttonSort && this.options.orderCount != this.orderCount) {
				result.index = this.options.orderIndex;
				result.count = this.options.orderCount;
			}
			result.data = this.additionalData;
			return result;
		},
		setData: function (result) {
			if (result.count !== undefined) {
				this.orderCount = result.count;
			}
			$.extend(this.additionalData, result.data);
		},
		setAdditionalData: function (data) {
			this.additionalData = data;
		}
	});
}(jQuery));
