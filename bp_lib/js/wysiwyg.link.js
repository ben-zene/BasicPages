/**
 * Controls: Link plugin
 *
 * Depends on jWYSIWYG
 *
 * By: Esteban Beltran (academo) <sergies@gmail.com>
 */
(function ($) {
	if (undefined === $.wysiwyg) {
		throw "wysiwyg.image.js depends on $.wysiwyg";
	}

	if (!$.wysiwyg.controls) {
		$.wysiwyg.controls = {};
	}

	/*
	* Wysiwyg namespace: public properties and methods
	*/
	$.wysiwyg.controls.link = {
		init: function (Wysiwyg) {
			var self = Wysiwyg, elements, dialog, szURL, a, selection,
				formLinkHtml, formTextLegend, formTextUrl, formTextTitle, formTextTarget,
				formTextSubmit, formTextReset;

			formTextLegend  = "Insert Link";
			formTextUrl     = "Link URL";
			formTextTitle   = "Link Title";
			formTextTarget  = "Link Target";
			formTextSubmit  = "Insert Link";
			formTextReset   = "Cancel";

			if ($.wysiwyg.i18n) {
				formTextLegend = $.wysiwyg.i18n.t(formTextLegend, "dialogs");
				formTextUrl    = $.wysiwyg.i18n.t(formTextUrl, "dialogs");
				formTextTitle  = $.wysiwyg.i18n.t(formTextTitle, "dialogs");
				formTextTarget = $.wysiwyg.i18n.t(formTextTarget, "dialogs");
				formTextSubmit = $.wysiwyg.i18n.t(formTextSubmit, "dialogs");
				formTextReset  = $.wysiwyg.i18n.t(formTextReset, "dialogs");
			}

			formLinkHtml = '<form class="wysiwyg"><fieldset><legend>' + formTextLegend + '</legend>' +
				'<label>' + formTextUrl + ': <input type="text" name="linkhref" value=""/></label>' +
				'<label>' + formTextTitle + ': <input type="text" name="linktitle" value=""/></label>' +
				'<label>' + formTextTarget + ': <input type="text" name="linktarget" value=""/></label>' +
				'<input type="submit" class="button" value="' + formTextSubmit + '"/> ' +
				'<input type="reset" value="' + formTextReset + '"/></fieldset></form>';

			a = {
				self: self.dom.getElement("a"), // link to element node
				href: "http://",
				title: "",
				target: ""
			};

			if (a.self) {
				a.href = a.self.href ? a.self.href : a.href;
				a.title = a.self.title ? a.self.title : "";
				a.target = a.self.target ? a.self.target : "";
			}

			if ($.fn.dialog) {
				elements = $(formLinkHtml);
				elements.find("input[name=linkhref]").val(a.href);
				elements.find("input[name=linktitle]").val(a.title);
				elements.find("input[name=linktarget]").val(a.target);

				if ($.browser.msie) {
					dialog = elements.appendTo(Wysiwyg.editorDoc.body);
				} else {
					dialog = elements.appendTo("body");
				}

				dialog.dialog({
					modal: true,
					width: self.defaults.formWidth,
					height: self.defaults.formHeight,
					open: function (ev, ui) {
						$("input:submit", dialog).click(function (e) {
							e.preventDefault();

							var szURL = $('input[name="linkhref"]', dialog).val(),
								title = $('input[name="linktitle"]', dialog).val(),
								target = $('input[name="linktarget"]', dialog).val();

							if (a.self) {
								if ("string" === typeof (szURL)) {
									if (szURL.length > 0) {
										// to preserve all link attributes
										$(a.self).attr("href", szURL).attr("title", title).attr("target", target);
									} else {
										$(a.self).replaceWith(a.self.innerHTML);
									}
								}
							} else {

								if ($.browser.msie) {
									self.ui.returnRange();
								}

								//Do new link element
								selection = this.range.apply(self);

								if (selection && selection.length > 0) {
									if ($.browser.msie) {
										self.ui.focus();
									}

									if ("string" === typeof (szURL)) {
										if (szURL.length > 0) {
											self.editorDoc.execCommand("createLink", false, szURL);
										} else {
											self.editorDoc.execCommand("unlink", false, null);
										}
									}

									a = self.dom.getElement("a");
									$(a).attr("href", szURL).attr("title", title).attr("target", target);
								} else if (self.options.messages.nonSelection) {
									window.alert(self.options.messages.nonSelection);
								}
							}
							$(dialog).dialog("close");
						});
						$("input:reset", dialog).click(function (e) {
							e.preventDefault();
							$(dialog).dialog("close");
						});
					},
					close: function (ev, ui) {
						dialog.dialog("destroy");
					}
				});
			} else {
				if (a.self) {
					szURL = window.prompt("URL", a.href);

					if ("string" === typeof (szURL)) {
						if (szURL.length > 0) {
							a.href = szURL;
						} else {
							$(a.self).replaceWith(a.self.innerHTML);
						}
					}
				} else {
					//Do new link element
					selection = this.range.apply(self);

					if (selection && selection.length > 0) {
						if ($.browser.msie) {
							self.ui.focus();
							self.editorDoc.execCommand("createLink", true, null);
						} else {
							szURL = window.prompt(formTextUrl, a.href);

							if ("string" === typeof (szURL)) {
								if (szURL.length > 0) {
									self.editorDoc.execCommand("createLink", false, szURL);
								} else {
									self.editorDoc.execCommand("unlink", false, null);
								}
							}
						}
					} else if (self.options.messages.nonSelection) {
						window.alert(self.options.messages.nonSelection);
					}
				}
			}

			$(self.editorDoc).trigger("wysiwyg:refresh");
		},

		range: function () {
			var r = this.getInternalRange();

			if (r.toString) {
				r = r.toString();
			} else if (r.text) {	// IE
				r = r.text;
			}

			return r;
		}
	}
})(jQuery);