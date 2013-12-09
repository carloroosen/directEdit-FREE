/*jslint nomen: true, browser: true */
/*global jQuery: false, console:false, FileReader: false */

(function ($) {
	"use strict";
	$.widget("directEdit.directImageEditor", {
		dialog: null,
		image: null,
		isImage: true,
		callback: null,
		textEditor: null,
		additionalData: {},
		options: {
			selector: 'img',
			ajaxUrl : '',
			constraints: { minWidth: 16, maxWidth: 1200, minHeight: 16, maxHeight: 1200 },
			dialogOptions : {
				width: 640,
				height: 500,
				title: "Edit image",
				modal: false,
				resizable: true,
				draggable: true,
				dialogClass: 'direct-edit'
			},
			uploadCommand : ['action', 'direct-upload-image'],
			editCommand : ['action', 'direct-edit-image'],
			scaleType : 'normal'
		},
		_create: function () {
			var selectImage, unSelectHandler, dblclickOpen, dialogOpenHandler, self;
			self = this;

			if (this.element.is('img')) {
				this.isImage = true;
				this.image = this.element;
				this.imageData = this.getImageData();
				if (this.options.modified) { this.modified = true; }
			} else {
				this.isImage = false;
				this.textEditor = this.element.data('directEdit-directTextEditor');
			}

			this.options.dialogOptions.autoOpen = false;
			this.dialog = $('<div id="direct-image-editor-dialog"></div>').dialog(this.options.dialogOptions);
			(function () {
				var selectedImage;
				selectImage = function (event) {
					if (self.isImage) {
						// standalone image
						selectedImage = $(event.target).addClass('direct-image-editor-active-image');
						unSelectHandler(selectedImage);
					} else {
						// image in rich text
						if ($(event.target).is(self.options.selector)) {
							// existing image selected
							self.image = selectedImage = $(event.target).addClass('direct-image-editor-active-image');
							unSelectHandler(self.image);
							self.imageData = self.getImageData();
							self.setAdditionalData($.directEdit.fn.getData(self.image));
						} else {
							// deselect, make ready to insert new image
							self.image = null;
							self.imageData = {};
							self.setAdditionalData();
						}
					}
				};
				dblclickOpen = function (event) {
					if ($(event.target).is(self.options.selector)) {
						self.dialog.dialog('open');
					}
				};
				dialogOpenHandler = function () {
					if (self.imageData.source) {
						self._createEditPage();
					} else {
						self._createUploadPage();
					}
				};
				unSelectHandler = function (image) { // create a closure that keeps a reference to the selected image
					var unSelectImage;
					unSelectImage = function (event) {
						if (event.target !== image[0]) {
							image.removeClass('direct-image-editor-active-image');
							$(document).off('mousedown', unSelectImage);
						}
					};
					$(document).on('mousedown', unSelectImage);
				};
			}());

			this._createSuccessHandler();
			this.element.mousedown(selectImage);
			this.element.dblclick(dblclickOpen);
			this.dialog.on('dialogopen', dialogOpenHandler);
		},
		_createSuccessHandler: function () {
			var self = this, newImage;
			this.callback = function (result) {
				if (self.options.callback) {
					result = result || {};
					self.options.callback.call(self, result);
				} else if (self.image) {
					newImage = self.image
						.after(result.content)
						.next()
						.directImageEditor(self.options) // a fresh and new editor
						.directImageEditor('setModified');
					self.image.remove();
				}
			};
		},
		getDialog: function () { return this.dialog; },
		_createEditPage: function () {
			var selectedStyle, style, styles, isFirstStyle, contentEdit, imageTypeButtons,
				editableImage, sliderContainer, editorOptions, toolBox, editForm, buttonSet,
				changeStyle, newImage, saveImageData, saveSourceImageSuccess, self;
			// A style is set of scaling constraints, styles must containe at least one style
			styles = this.options.styles;
			if (!styles) {
				styles = {};
				styles.single = {
					constraints: this.options.constraints
				};
			}
			self = this;

			// Select a style. Priority: 1) in data, 2) as default in styles, 3) first style in styles
			selectedStyle = this.imageData.style;
			if (selectedStyle === undefined || styles[selectedStyle] === undefined) {
				isFirstStyle = true;
				for (style in styles) {
					if (styles.hasOwnProperty(style)) {
						if (isFirstStyle || styles[style].isDefault) {
							selectedStyle = style;
							this.imageData.style = style;
							isFirstStyle = false;
						}
					}
				}
			}

			// create buttons for each style
			imageTypeButtons = '';
			function createImageTypeButton(style, isChecked) {
				return '<div class="select_image_type"><input type="radio" name="image_style" value="' + style + '" class="select_image_style" id="select_image_style_' + style + '"' + ((isChecked) ? ' checked="checked"' : '') + ' /><label for="select_image_style_' + style + '" class="' + style + '" title="' + style + '"></label></div>';
			}
			if (Object.keys(styles).length > 1) {
				for (style in styles) {
					if (styles.hasOwnProperty(style)) {
						imageTypeButtons += createImageTypeButton(style, (selectedStyle === style));
					}
				}
			}
			//build DOM
			contentEdit = $('<div id="direct-image-editor-dialog-editpage">');
			toolBox = $('<div class="toolbox">').appendTo(contentEdit);
			editForm = $('<form action="" id="selfForm" method="post">').appendTo(toolBox);
			editForm.append('<input type="hidden" name="image_class" id="image_class" value="inline" />');
			editForm.append(imageTypeButtons);
			editForm.append('<div style="clear:both; height:10px;"></div>');
			editForm.append('<div class="input_text"><label for="alt">alt:</label><input type="text" name="alt" id="alt" value="' + (this.imageData.alt || '') + '" /></div>');
			buttonSet = $('<div class="buttonset"><input type="button" id="imageReset" value="' + directTranslate("Different image") + '" /><input type="button" id="sendForm" value="' + directTranslate("Save") + '"></div>');
			buttonSet.append('<div class="spinner"></div>');
			buttonSet.buttonset().appendTo(editForm);
			sliderContainer = $('<div class="sliderContainer">').appendTo(toolBox);
			editableImage = $('<div class="editableImage" style="overflow:hidden; width:' + (this.imageData.containerW || '0') + 'px; height:' + (this.imageData.containerH || '0') + 'px;"><img src="' + this.imageData.source + '" style="position:relative; top:' + (this.imageData.top || '0') + 'px; left:' + (this.imageData.left || '0') + 'px; width:' + (this.imageData.imageScaledW || '0') + 'px; height:' + (this.imageData.imageScaledH || '0') + 'px;" alt="" /></div>');
			editableImage.appendTo(contentEdit);

			// create editable image
			editorOptions = {sliderContainer : sliderContainer};
			if (this.options.scaleType) { $.extend(editorOptions, { 'scaleType' : this.options.scaleType }); }
			if (this.options.viewScale) { $.extend(editorOptions, { 'viewScale' : this.options.viewScale }); }
			$.extend(editorOptions, styles[selectedStyle].constraints);
			editableImage.directImageEditorCPS(editorOptions);

			// event handlers closure
			(function () {
				changeStyle = function () {
					editableImage.directImageEditorCPS(styles[$(this).attr('value')].constraints);
					self.imageData.style = $(this).attr('value');
				};
				newImage = function (event) {
					// prevent the dialog from being closed by the pseudo-blur detector
					event.stopPropagation();
					self._createUploadPage();
				};
				saveImageData = function () {
					var saveData  = {data: {}};
					$.extend(saveData.data, self.additionalData, editableImage.directImageEditorCPS('getState'));
					saveData.data.style = self.imageData.style;
					// saveData.data['class'] = styles[self.imageData.style]['class'];
					// saveData.data.factor = styles[self.imageData.style].factor;
					saveData.data.alt = contentEdit.find('input#alt').val();
					saveData[self.options.editCommand[0]] = self.options.editCommand[1];
					self.saveImageData(saveData, self.callback);
					self.dialog.dialog('close');
					self.modified = true;
				};
				saveSourceImageSuccess = function (ret) {
					if (ret.data) {
						$.extend(self.additionalData, ret.data);
					}
					contentEdit.find('#sendForm').button('enable');
					$('.spinner', contentEdit).hide();
				};
			}());

			// bind event handlers
			contentEdit.find('input.select_image_style').change(changeStyle);
			contentEdit.find('#imageReset').click(newImage);
			contentEdit.find('#sendForm').click(saveImageData);

			// upload source image file in the background
			if (this.imageData.file) {
				$('.spinner', contentEdit).show();
				contentEdit.find('#sendForm').button('disable');
				this.saveSourceImage(this.additionalData, this.imageData.file, saveSourceImageSuccess);
			}

			// append the whole thing to the dialog container
			this.dialog.empty().append(contentEdit);
		},
		_createUploadPage: function () {
			var contentUpload, saveSourceImageSuccess, self;
			self = this;

			contentUpload = $('<div><form><input type="file" name="file" /></form></div>');
			contentUpload.find(':file').change(function () {
				var file, reader;
				file = this.files[0]; // FileList object

				// Only process image files.
				if (!file.type.match('image.*')) {
					return;
				}

				if (!FileReader) {
					// in Safari we cannot upload the the image in the background
					saveSourceImageSuccess = function (ret) {
						if (ret.data) { $.extend(self.additionalData, ret.data); }
						if (ret.url) { self.imageData.source = ret.url; }
						self._createEditPage();
					};
					$('<div id="spinner"></div>').appendTo(contentUpload).show();
					self.saveSourceImage(self.additionalData, file, saveSourceImageSuccess);
				} else {
					reader = new FileReader();

					// Closure to capture the file information.
					reader.onload = (function (_file) {
						return function (e) {
							var imageData;
							// go to next step
							imageData = e.target.result;
							self._createConfirmPage(imageData, _file);
						};
					}(file));

					reader.onerror = function () { directNotify(directTranslate("Error reading image.")); };

					// Read in the image file as a data URL.
					reader.readAsDataURL(file);
				}
			});
			this.dialog.empty().append(contentUpload);
		},
		_createConfirmPage: function (imageData, file) {
			var contentConfirm, self, thumb, afterImageLoad;
			self = this;
			contentConfirm = $('<div><div id="imageUploadButtons"></div><div id="imageThumb" style="margin-top:10px;"></div></div>');
			thumb = $("<img>");
			thumb.hide();
			contentConfirm.find('#imageThumb').append(thumb);
			function checkImageSize(image) {
				if (self.options.scaleType === 'normal') {
					if ((self.options.constraints.minWidth && image.width() < self.options.constraints.minWidth) || (self.options.constraints.minHeight && image.height() < self.options.constraints.minHeight)) {
						contentConfirm.append('<p>The image is too small, it should be ' + self.options.constraints.minWidth + 'px x ' + self.options.constraints.minHeight + 'px, and it is ' + image.width() + 'px x ' + image.height() + 'px</p>');
						return false;
					}
				} else {
					if ((self.options.constraints.minWidth && image.width() < self.options.constraints.minWidth) && (self.options.constraints.minHeight && image.height() < self.options.constraints.minHeight)) {
						if (self.options.constraints.minWidth && image.width() < self.options.constraints.minWidth) {
							contentConfirm.append('<p>' + directTranslate("The image width is too small, it should be") + ' ' + self.options.constraints.minWidth + 'px' + directTranslate("and it is") + ' ' + image.width() + 'px.</p>');
						}
						if (self.options.constraints.minHeight && image.height() < self.options.constraints.minHeight) {
							contentConfirm.append('<p>' + directTranslate("The image height is too small, it should be") + ' ' + self.options.constraints.minHeight + 'px' + directTranslate("and it is") + ' ' + image.height() + 'px.</p>');
						}
						return false;
					}
				}
				return true;
			}
			afterImageLoad = function () {
				var buttonset = contentConfirm.find('#imageUploadButtons');
				$('<input type="button" id="imageReset" value="' + directTranslate('Andere afbeelding') + '" />').appendTo(buttonset)
					.click(function (event) {
					// prevent the dialog from being closed by the pseudo-blur detector
						event.stopPropagation();
						self._createUploadPage();
					});
				if (checkImageSize(thumb)) {
					thumb.css('maxWidth', 360);
					thumb.show();
					$('<input type="button" id="imageUploadButton" value="' + directTranslate('Uploaden en bewerken') + ' >>" />').appendTo(buttonset)
						.click(function (event) {
							// prevent the dialog from being closed by the pseudo-blur detector
							event.stopPropagation();
							self.imageData = {};
							self.imageData.source = imageData;
							self.imageData.alt = file.name.split('.')[0];
							self.imageData.file = file; // not uploaded yet
							self._createEditPage();
						});
				}
				buttonset.buttonset();
			};
			thumb.on('load', function () {afterImageLoad(); });
			thumb.attr('src', imageData);
			this.dialog.empty().append(contentConfirm);
		},
		saveSourceImage: function (additionalData, file, callback) {
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
					window.alert(directTranslate('Image uploading failed.'));
					$('#direct-image-editor-dialog-editpage .spinner').hide();
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
		},
		saveImageData: function (data, callback) {
			$.ajax({
				url: this.options.ajaxUrl || $.directEdit.fn.ajaxUrl,
				type: 'POST',
				error: function () {
					window.alert(directTranslate('Modifications could not be saved.'));
				},
				dataType: 'json',
				success: callback,
				data: data
			});
		},
		isModified: function () {
			return this.modified || false; // if falsy then false
		},
		setModified: function () {
			this.modified = true;
		},
		getImageData: function () {
			var res, reserved, value, tempData;
			reserved = ['containerW', 'containerH', 'imageScaledH', 'imageScaledW', 'top', 'left', 'source', 'style'];
			tempData = {};
			for (res in reserved) {
				if (reserved.hasOwnProperty(res)) {
					value = this.image.attr('data-' + [reserved[res].toLowerCase()]);
					if (value) {
						tempData[reserved[res]] = value;
					}
				}
			}
			value = this.image.attr('alt');
			if (value) { tempData.alt = value; }
			return tempData;
		},
		getData: function () {
			if (this.image) {
				return {
					content: this.image[0].outerHTML,
					data: this.additionalData
				};
			}
		},
		setData: function (data) {
			var newEditor;
			if (this.isImage) {
				newEditor = this.image
					.after(data.content)
					.next()
					.directImageEditor(this.options) // a fresh and new editor
					.data("directEdit-directImageEditor");
				this.image.remove();
				return newEditor;
			}
		},
		setAdditionalData: function (data) {
			this.additionalData = {};
			if (this.textEditor) {
				$.extend(this.additionalData, this.textEditor.additionalData);
			}
			$.extend(this.additionalData, data);
		}
	});

/*
 * directImageEditorCPS a $ UI widget for image editing, part of the directEdit project
 * (c) 2010-2013 Carlo Roosen (http://www.carloroosen.nl)
 */

	$.widget("directEdit.directImageEditorCPS", {
		// state of scaling and cropping
		imageScaledW : 0,
		imageScaledH : 0,
		imageSrcW : 0,
		imageSrcH : 0,
		relativeX : 0.5,
		relativeY : 0.5,
		containerW : 0,
		containerH : 0,

		// UI elements
		container : null,
		image : null,
		slider : null,

		// defaults options
		options: {
			// resizing constraints
			minWidth: 100,
			minHeight: 70,
			maxWidth: 1200,
			maxHeight: 1200,

			// resizable handles, handles that cannot be used will be hidden automatically 
			handles: 'e, s, se',

			// scale the view of the editor THIS OPTION IS EXPERIMENTAL
			viewScale : 1,

			// hide UI elements in image when moving mouse away 
			autoHide : true,

			// any jQuery element that can hold a slider, if not provided it will be created on top of the image
			sliderContainer : null,

			// scaleType defines the behaviour when ratios between image and container dont match
			// 'normal' will crop the image, 'fit' will show background color (actual color must be defined server-side)
			// scaleType also defines the behaviour for images that are smaller than the minimum container size
			// normal will overwrite minimum container size options, 'fit' will place the image on a canvas with background color.
			scaleType : 'normal'
		},
		// create the widget
		_create: function () {
			// build the DOM
			var container, image, imgUrl, tempImage, self;
			container = this.container = this.element;
			image = this.image = container.children('img');
			if ((!container.is('div') || image.length !== 1)) {
				throw "directImageEditorCPS must be applied on a div containing a single img.";
			}
			container.addClass('image-editor-container');
			this.slider = $('<div class="image-editor-slider" />');
			if (this.options.sliderContainer) {
				this.options.sliderContainer.append(this.slider);
			} else {
				image.after(this.slider);
				this.slider.wrap('<div class="ui-slider-container"/>');
			}

			// create a copy of the image to retrieve the original image size. This method works best for different browsers.
			imgUrl = image.attr('src');
			tempImage = $("<img>");
			tempImage.hide();
			self = this;
			container.append(tempImage);
			// first set up the event handler
			tempImage.on('load', function () { self._fetchSourceSize(tempImage); });
			// setting the source will invoke the load event after the image is loaded
			tempImage.attr('src', imgUrl);
		},
		_init : function () {
			// only used when the options are changed
			if (this.imageSrcW !== 0 || this.imageSrcH !== 0) {
				this.container.resizable("destroy");
				this.container.off('hover');
				this.container.children().show();
				this._initialize();
			}
		},
		_fetchSourceSize : function (tempImage) {
			if (tempImage.width() === 0 || tempImage.height() === 0) { throw ('Could not fetch image size'); }
			this.imageSrcW = tempImage.width();
			this.imageSrcH = tempImage.height();
			tempImage.remove();
			this._initialize();
		},
		_initialize: function () {
			var thisEditor, container, image, divW, divH, scale, scaleMin, startScale, containment,
				storedContainerW, storedContainerH, uiElements;
			// we dont use the native _init() function, because it would be called too early, before the source image size is known
			if (this.imageSrcW === 0 || this.imageSrcH === 0) {	throw ('Could not detect image size'); }

			// create a reference to 'this' that also works inside functions
			thisEditor = this;
			container = this.container;
			image = this.image;

			// retrieve image scale and position from css.
			// If css width and height is not set or auto, assume it is a new image and zoom out
			if (image.css('width') !== 'auto' && image.css('height') !== 'auto') {
				thisEditor.imageScaledW = image.width();
				thisEditor.imageScaledH = image.height();
				divW = container.width() - image.width();
				divH = container.height() - image.height();
				thisEditor.relativeX = (divW !== 0) ? (parseInt(image.css('left'), 10) / divW) : thisEditor.relativeX;
				thisEditor.relativeY = (divH !== 0) ? (parseInt(image.css('top'), 10) / divH) : thisEditor.relativeY;
				thisEditor.relativeX = Math.min(1, Math.max(0, thisEditor.relativeX));
				thisEditor.relativeY = Math.min(1, Math.max(0, thisEditor.relativeY));
			} else {
				thisEditor.imageScaledW = 0;  // will be adjusted later
				thisEditor.imageScaledH = 0;
			}

			// limit maximum size to image size. Not for scaletype 'fit', here small images will be displayed on a background color
			if (thisEditor.options.scaleType !== 'fit') {
				thisEditor.options.maxWidth = Math.min(thisEditor.options.maxWidth, thisEditor.imageSrcW);
				thisEditor.options.maxHeight = Math.min(thisEditor.options.maxHeight, thisEditor.imageSrcH);
			}
			// minimum values must be equal to or smaller than maximum values
			thisEditor.options.minWidth = Math.min(thisEditor.options.minWidth, thisEditor.options.maxWidth);
			thisEditor.options.minHeight = Math.min(thisEditor.options.minHeight, thisEditor.options.maxHeight);

			// container size validation
			thisEditor.containerW = Math.max(Math.min(container.width(), thisEditor.options.maxWidth), thisEditor.options.minWidth);
			thisEditor.containerH = Math.max(Math.min(container.height(), thisEditor.options.maxHeight), thisEditor.options.minHeight);

			// validate image scaling
			scale = Math.max(thisEditor.imageScaledW / thisEditor.imageSrcW, thisEditor.imageScaledH / thisEditor.imageSrcH);
			scaleMin = thisEditor._getScaleMin();
			scale = Math.min(Math.max(scale, scaleMin), 1);
			thisEditor.imageScaledW = thisEditor.imageSrcW * scale;
			thisEditor.imageScaledH = thisEditor.imageSrcH * scale;

			// Resizable Container
			container.resizable({
				start : function () {
					startScale = Math.max(thisEditor.imageScaledW / thisEditor.imageSrcW, thisEditor.imageScaledH / thisEditor.imageSrcH);
				},
				resize : function (e, ui) {
					var scaleW, scaleH, scaleMin, scale;
					thisEditor.containerW = ui.size.width / thisEditor.options.viewScale;
					thisEditor.containerH = ui.size.height / thisEditor.options.viewScale;
					scaleW = thisEditor.containerW / thisEditor.imageSrcW;
					scaleH = thisEditor.containerH / thisEditor.imageSrcH;
					if (thisEditor.options.scaleType === 'fit') {
						scaleMin = Math.min(Math.min(scaleW, scaleH), 1);
					} else {
						scaleMin = Math.max(scaleW, scaleH);
					}
					scale = Math.max(scaleMin, startScale);
					thisEditor.imageScaledW = thisEditor.imageSrcW * scale;
					thisEditor.imageScaledH = thisEditor.imageSrcH * scale;
					thisEditor._setSlider(scale);
					thisEditor._updateView();
				},
				maxWidth : thisEditor.options.maxWidth * thisEditor.options.viewScale,
				maxHeight : thisEditor.options.maxHeight * thisEditor.options.viewScale,
				minWidth : thisEditor.options.minWidth * thisEditor.options.viewScale,
				minHeight : thisEditor.options.minHeight * thisEditor.options.viewScale,
				handles : thisEditor._getHandles()
			});

			// Draggable Image
			image.draggable({
				start: function () {
					containment = thisEditor._getContainment();
				},
				drag: function (e, ui) {
					if (ui.position.left < containment.minX) { ui.position.left = containment.minX; }
					if (ui.position.left > containment.maxX) { ui.position.left = containment.maxX; }
					if (ui.position.top < containment.minY) { ui.position.top = containment.minY; }
					if (ui.position.top > containment.maxY) { ui.position.top = containment.maxY; }
				},
				stop: function (e, ui) {
					thisEditor.relativeX = (thisEditor.containerW - thisEditor.imageScaledW !== 0) ? ui.position.left / ((thisEditor.containerW - thisEditor.imageScaledW) * thisEditor.options.viewScale) : thisEditor.relativeX;
					thisEditor.relativeY = (thisEditor.containerH - thisEditor.imageScaledH !== 0) ? ui.position.top / ((thisEditor.containerH - thisEditor.imageScaledH) * thisEditor.options.viewScale) : thisEditor.relativeY;
				}
			});

			// Create Slider
			thisEditor.slider.slider({
				values: [0],
				max: 1000,
				start: function () {
					storedContainerW = thisEditor.containerW;
					storedContainerH = thisEditor.containerH;
				},
				slide: function (e, ui) {
					var sliderScaleMin, slideVal, scale;
					sliderScaleMin = thisEditor._getScaleMin();
					slideVal = Math.max(0, Math.min((ui.value / 1000), 1));
					scale = sliderScaleMin + ((1 - sliderScaleMin) * slideVal);
					thisEditor.imageScaledW = thisEditor.imageSrcW * scale;
					thisEditor.imageScaledH = thisEditor.imageSrcH * scale;
					if (thisEditor.options.scaleType !== 'fit') {
						thisEditor.containerW = Math.min(storedContainerW, thisEditor.imageScaledW);
						thisEditor.containerH = Math.min(storedContainerH, thisEditor.imageScaledH);
					}
					thisEditor._updateView();
				}
			});

			// Autohide ui elements
			if (thisEditor.options.autoHide) {
				uiElements = container.children().not('img').hide();
				container.hover(
					function () {
						uiElements.stop(true, true).fadeIn(300);
					},
					function () {
						uiElements.stop(true, true).delay(2000).fadeOut(300);
					}
				);
			}

			// Modify css (functional requirements)
			image.css({
				'position': 'absolute',
				'cursor': 'move',
				'min-width': 0,
				'min-height': 0,
				'max-width': 'none',
				'max-height': 'none',
				'border': 'none',
				'padding': '0',
				'margin': '0'
			});

			container.css({
				'overflow': 'hidden'
			});
			thisEditor._updateView();
			thisEditor._setSlider(scale);
		},
		_updateView : function () {
			var imageX, imageY;
			this.image.css('width', this.imageScaledW * this.options.viewScale);
			this.image.css('height', this.imageScaledH * this.options.viewScale);
			this.container.css('width', this.containerW * this.options.viewScale);
			this.container.css('height', this.containerH * this.options.viewScale);
			imageX = (this.containerW - this.imageScaledW) * this.relativeX * this.options.viewScale;
			imageY = (this.containerH - this.imageScaledH) * this.relativeY * this.options.viewScale;
			this.image.css('left', imageX + 'px');
			this.image.css('top', imageY + 'px');
		},
		_getScaleMin : function () {
			var scaleMin;
			if (this.options.scaleType === 'fit') {
				scaleMin = Math.min(this.options.minWidth / this.imageSrcW, this.options.minHeight / this.imageSrcH);
			} else {
				scaleMin = Math.max(this.options.minWidth / this.imageSrcW, this.options.minHeight / this.imageSrcH);
			}
			return scaleMin;
		},
		_setSlider : function (scale) {
			var sliderScaleMin, sliderValue;
			sliderScaleMin = this._getScaleMin();
			sliderValue = 1000 * (scale - sliderScaleMin) / (1 - sliderScaleMin);
			this.slider.slider('values', 0, sliderValue);
		},
		_getContainment : function () {
			// draggable ui position bounderies
			var containment = [];
			containment.minX = Math.min(this.containerW - this.imageScaledW, 0) * this.options.viewScale;
			containment.minY = Math.min(this.containerH - this.imageScaledH, 0) * this.options.viewScale;
			containment.maxX = Math.max(this.containerW - this.imageScaledW, 0) * this.options.viewScale;
			containment.maxY = Math.max(this.containerH - this.imageScaledH, 0) * this.options.viewScale;
			return containment;
		},
		_getHandles : function () {
			var i, j, handlesIntersect = [], handlesPossible = [], handlesOption;
			// find the intersection of handles defined in options and possible handles
			if (this.options.maxWidth > this.options.minWidth) { handlesPossible.push('e', 'w'); }
			if (this.options.maxHeight > this.options.minHeight) { handlesPossible.push('n', 's'); }
			if (this.options.maxWidth > this.options.minWidth || this.options.maxHeight > this.options.minHeight) { handlesPossible.push('ne', 'se', 'sw', 'nw'); }
			handlesOption = this.options.handles.split(',');
			for (i = 0; i < handlesOption.length; i += 1) {
				handlesOption[i] = handlesOption[i].replace(/^\s\s*/, '').replace(/\s\s*$/, '');
				for (j = 0; j < handlesPossible.length; j += 1) {
					if (handlesPossible[j] === handlesOption[i]) { handlesIntersect.push(handlesPossible[j]); }
				}
			}
			if (handlesIntersect.length === 0) { handlesIntersect.push('X'); } // force overwriting of default handles
			return handlesIntersect.join();
		},
		getState : function () {
			return ({
				imageScaledW : Math.round(this.imageScaledW),
				imageScaledH : Math.round(this.imageScaledH),
				left : Math.round(this.relativeX * (this.containerW - this.imageScaledW)),
				top : Math.round(this.relativeY * (this.containerH - this.imageScaledH)),
				containerW : Math.round(this.containerW),
				containerH : Math.round(this.containerH)
			});
		}
	});
}(jQuery));

