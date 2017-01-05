/*jslint nomen: true, browser: true */
/*global jQuery: false, console:false, FileReader: false */

(function ($) {
	"use strict";

	$.widget("directEdit.directFileUploader", {
		dialog: null,
		callback: null,
		additionalData: {},
		options: {
			ajaxUrl : '',
			dialogOptions : {
				width: 540,
				height: 95,
				title: "Upload file",
				modal: false,
				resizable: false,
				draggable: false,
				autoOpen: false,
				dialogClass: 'direct-edit'
			},
			followButtonText : "open",
			editButtonText : "upload",
			uploadCommand : ['action', 'direct-upload-file']
		},
		_create: function () {
			var dialogOpenHandler, self, buttons;
			self = this;
			if (this.element.is('a')) {
				this.element.addClass('direct-file-uploader');
				if ($.directEdit.directManager) {
					this.instanceID = $.directEdit.directManager.register(this, this.element.attr('id'));
				}
				this.originalUrl = this.url = this.element.attr('href');
				buttons = $('<div class="direct-file-uploader-buttons">');
				this.follow = $('<a target="_blank">' + this.options.followButtonText + '</a>').attr('href', this.url).appendTo(buttons);
				$('<a href="">' + this.options.editButtonText + '</a>').appendTo(buttons).click(function () {
					self.dialog.dialog('open');
					return false;
				});
				this.element.removeAttr('href').addClass('link-editor').prepend(buttons);
			}
			this.dialog = $('<div id="direct-file-uploader-dialog"></div>').dialog(this.options.dialogOptions);
			(function () {
				dialogOpenHandler = function () {
					var textEditor;
					if (self.element.data('directEdit-directTextEditor')) {
						textEditor = self.element.data('directEdit-directTextEditor');
						self.additionalData = textEditor.additionalData;
						console.log(self.additionalData);
					}
					self._createUploadPage();
				};
			}());
			this.dialog.on('dialogopen', dialogOpenHandler);
		},
		isModified : function () {
			return (this.url !== this.originalUrl);
		},
		getData : function () {
			return {
				data : this.additionalData,
				url : this.url
			};
		},
		setData: function (result, temp) {
			// not stored: result.filename, result.extension, these can be used in custom callback
			if (result.url) { this.url = result.url; }
			if (this.follow) { this.follow.attr('href', this.url); }
			if (!temp) { this.originalUrl = this.url; }
			$.extend(this.additionalData, result.data);
		},
		setAdditionalData: function (data) {
			this.additionalData = data;
		},
		getDialog: function () {
			return this.dialog;
		},
		_createUploadPage: function () {
			var contentUpload, successHandler, self;
			self = this;
			(function () {
				successHandler = function (result) {
					self.dialog.dialog('close');
					if (self.options.callback && result) {
						self.options.callback.call(self, result);
					} else {
						self.setData(result, true);
					}
				};
			}());

			// data retreived from this.element, can be the image or the container (eg text editor)
			contentUpload = $('<div><form><input type="file" name="file" /></form></div>');
			contentUpload.find(':file').change(function () {
				var file, spinner;
				file = this.files[0]; // FileList object
				// upload  file in the background
				spinner = $('#spinner', contentUpload).show();
				self.saveFile(self.additionalData, file, successHandler);
			});
			this.dialog.empty().append(contentUpload);
		},
		saveFile: function (additionalData, file, callback) {
			var formData = new FormData(), dataElement;
			formData.append('file', file);
			formData.append(this.options.uploadCommand[0], this.options.uploadCommand[1]);
			for (dataElement in additionalData) {
				if (additionalData.hasOwnProperty(dataElement) && dataElement !== 'file') {
					formData.append('data[' + dataElement + ']', additionalData[dataElement].toString());
				}
			}
			$.ajax({
				url: this.options.ajaxUrl || $.directEdit.fn.ajaxUrl,
				type: 'POST',
				xhr: function () {  // custom xhr
					var myXhr = $.ajaxSettings.xhr();
					return myXhr;
				},
				//Ajax events
				error: function () {
					window.alert('Kan bestand niet uploaden');
					// spinner.hide();
				},
				success: callback,
				// Form data
				data: formData,
				// return data type
				dataType: 'json',
				//let jQuery not interfere
				cache: false,
				contentType: false,
				processData: false
			});
		}
	});
}(jQuery));

