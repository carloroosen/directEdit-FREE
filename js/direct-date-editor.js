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
			
			this.dateOld = this.dateNew = this.element.attr('data-date').substring(0,10); 
			this.input = $('<input value="' + this.dateOld + '" />').css({'visibility':'hidden','position':'absolute'}).insertAfter(this.element);
			this.input.datepicker({
				dateFormat: 'yy-mm-dd',
				onSelect: function( selectedDate ) {
					self.dateNew = selectedDate;
					self.element.html($.datepicker.formatDate(self.options.format, new Date(selectedDate )));
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
			result.content = this.dateNew + ' 00:00:00';
			result.data = this.additionalData;
			return result;
		},
		setData: function (result) {
			this.dateNew = this.dateOld = result.content.substring(0,10);
			this.input.datepicker('setDate', this.dateNew);
			this.element.html($.datepicker.formatDate(this.options.format, new Date(this.dateNew)));
			$.extend(this.additionalData, result.data);
		},
		setAdditionalData: function (data) {
			delete data.date;
			delete data['date-datepicker'];
			this.additionalData = data;
		}
	});
}(jQuery));
