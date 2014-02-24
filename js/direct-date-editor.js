/*jslint nomen: true, browser: true */
/*global jQuery: false, console: false */

(function ($) {
	"use strict";
	$.widget("directEdit.directDateEditor", {
		additionalData: null,
		options: {
			format: 'MM dd yy'
		},
		_create: function () {
			var self = this;
			
			this.dateOld = this.element.attr('data-date');
			this.input = $('<input value="' + this.element.attr('data-date-datepicker') + '" />').css({'visibility':'hidden','position':'absolute'}).insertAfter(this.element);
			this.input.datepicker({
				dateFormat: 'yy-mm-dd',
				onSelect: function( dateText ) {
					self.dateNew = dateText;
					self.element.html($.datepicker.formatDate(self.options.format, new Date(self.dateNew )));
				}
			});
			this.element.click(function(){
				self.input.datepicker('show');
			});
		
		},
		isModified: function () {
			return this.dateOld !== this.dateNew;
		},
		getData: function () {
			var result = {};
			result.content = this.dateNew;
			result.data = this.additionalData;
			return result;
		},
		setData: function (result) {
			this.dateNew = this.dateOld = result.content;
			$.extend(this.additionalData, result.data);
		},
		setAdditionalData: function (data) {
			delete data.date;
			delete data['date-datepicker'];
			this.additionalData = data;
		}
	});
}(jQuery));
