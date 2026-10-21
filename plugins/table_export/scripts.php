<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js" integrity="sha512-dlPw+ytv/6JyepmelABrgeYgHI0O+frEwgfnPdXDTOIZz+eDgfW07QXG02/O8COfivBdGNINy+Vex+lYmJ5rxw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js" referrerpolicy="no-referrer"></script>
<script>
	/**
	 * Cell wrappers from createInput() often omit input_type; infer from content so export matches
	 * explicit input_type handling (see constructor_swap_functions.php).
	 */
	function tableExportCellInputType (cell) {
		const attr = cell.getAttribute('input_type');
		if (attr) return attr;
		if (cell.querySelector('checkboxes_in_table, input[type="checkbox"]')) return 'db_checkboxes';
		if (cell.querySelector('radiobox, input[type="radio"]')) return 'radio';
		if (cell.querySelector('select')) return 'db_selector';
		if (cell.querySelector('input[type="number"]')) return 'number';
		if (cell.querySelector('a[href^="mailto:"]')) return 'mailto';
		if (cell.querySelector('.input_type_display')) return 'display';
		if (cell.querySelector('textarea')) return 'text';
		if (cell.querySelector('input:not([type="hidden"])')) return 'text';
		return 'display';
	}

	// Field content may be nested (e.g. row > div > .input_type_display or row > div > a > .input_type_display).
	const tableExportFieldSelector = '.input_type_display, input, textarea, select, checkboxes_in_table, checkboxes, radiobox, a[href^="mailto:"]';

	function tableExportCellLabel (cell) {
		const label = cell.querySelector('label, .label');
		return label ? label.textContent.trim() : cell.innerText.trim();
	}

	function tableExportGridRowCells (row) {
		const cells = [];
		row.querySelectorAll(':scope > div.row').forEach(section => {
			section.querySelectorAll(':scope > div').forEach(cell => {
				if (cell.querySelector(tableExportFieldSelector)) {
					cells.push(cell);
				}
			});
		});
		return cells;
	}

	function tableExportRowCells (row) {
		// Grid / row template: <row> > <div class="row"> > <div> per field (label + input).
		if (row.querySelector(':scope > div.row')) {
			return tableExportGridRowCells(row);
		}
		const directCells = Array.from(row.querySelectorAll(':scope > div'));
		return directCells.filter(c => c.querySelector(tableExportFieldSelector));
	}

	function getExportCellValue (cell) {
		let celVal = false;
		switch (tableExportCellInputType(cell)) {
			case 'display': {
				const disp = cell.querySelector('.input_type_display');
				if (disp) {
					celVal = disp.textContent.trim();
				} else if (cell.firstElementChild) {
					celVal = cell.firstElementChild.textContent;
				}
				break;
			}
			case 'mailto': {
				let mail = cell.querySelector('input[type="hidden"]');
				if (mail != null) {
					celVal = mail.value;
				}
				break;
			}
			case 'text': {
				let field = cell.querySelector('input:not([type="hidden"]), textarea');
				if (field) {
					celVal = field.value;
				}
				break;
			}
			case 'db_checkboxes' :
				if (cell.querySelector('input:checked') != null) {
					celVal = 'X';
				} else {
					celVal = '';
				}
				break;
			case 'radio': {
				const radioRoot = cell.matches('radiobox') ? cell : cell.querySelector('radiobox');
				const checked = (radioRoot || cell).querySelector('input[type="radio"]:checked');
				if (checked) {
					const lbl = cell.querySelector('label[for="' + checked.id + '"]');
					celVal = lbl ? lbl.textContent.trim() : checked.value;
				}
				break;
			}
			case 'number': {
				let num = cell.querySelector('input[type="number"]');
				if (num) {
					celVal = Number(num.value);
				}
				break;
			}
			case 'db_selector': {
				let sel = cell.querySelector('select');
				if (sel) {
					let opt = sel.options[sel.selectedIndex];
					if (opt != null && !opt.disabled) {
						celVal = opt.textContent;
					}
				}
				break;
			}
		}
		return celVal === false ? '' : celVal;
	}

	function tableExportNumFmtFromValues (values) {
		let maxDecimals = 0;
		let hasNumber = false;
		values.forEach(v => {
			if (v === '' || v === null || v === undefined) return;
			if (typeof v !== 'number' || !Number.isFinite(v)) return;
			hasNumber = true;
			if (Number.isInteger(v)) return;
			const frac = String(v).split('.')[1];
			if (frac) {
				maxDecimals = Math.max(maxDecimals, frac.length);
			}
		});
		if (!hasNumber) return null;
		if (maxDecimals === 0) return '0';
		return '0.' + '0'.repeat(maxDecimals);
	}

	function tableExportPxToColWidth (px) {
		return Math.max(8, Math.min(60, px / 7));
	}

	function tableExportDomColumnWidths (cells) {
		return Array.from(cells).map(c => tableExportPxToColWidth(c.offsetWidth || 0));
	}

	function tableExportTabTitle (container) {
		const key = container.getAttribute('key');
		if (!key) return null;
		const h2 = editor.querySelector('#table_title_' + key);
		return h2 != null ? h2.textContent.trim() : null;
	}

	function tableExportSanitizeSheetName (name) {
		return String(name).replace(/[\\\/\?\*\[\]:]/g, '_').substring(0, 31);
	}

	function tableExportUniqueSheetName (baseName, containerKey, usedNames) {
		let name = tableExportSanitizeSheetName(baseName);
		if (!usedNames.has(name)) {
			usedNames.add(name);
			return name;
		}
		const keySuffix = containerKey ? '_' + String(containerKey).replace(/[\\\/\?\*\[\]:]/g, '_') : '';
		let candidate = tableExportSanitizeSheetName(
			String(baseName).substring(0, Math.max(1, 31 - keySuffix.length)) + keySuffix
		);
		let n = 2;
		while (usedNames.has(candidate)) {
			const numSuffix = ' (' + n + ')';
			candidate = tableExportSanitizeSheetName(
				String(baseName).substring(0, Math.max(1, 31 - numSuffix.length)) + numSuffix
			);
			n++;
		}
		usedNames.add(candidate);
		return candidate;
	}

	function tableExportTabHeaderRow (container, colCount) {
		const raw = container.getAttribute('data-tab-header-row');
		if (!raw || !colCount) return null;
		let tabHeaderMap;
		try {
			tabHeaderMap = JSON.parse(raw);
		} catch (e) {
			return null;
		}
		const row = [];
		for (let i = 0; i < colCount; i++) {
			if (tabHeaderMap[i] === undefined || tabHeaderMap[i] === null) {
				row.push('');
			} else {
				const el = document.createElement('div');
				el.innerHTML = String(tabHeaderMap[i]);
				row.push(el.textContent.trim());
			}
		}
		return row;
	}

	function tableExportApplySheetLayout (ws, opts) {
		const titleRowNum = opts.titleRowNum;
		const tabTitleRowNum = opts.tabTitleRowNum;
		const tabHeaderRowNum = opts.tabHeaderRowNum;
		const headerRowNum = opts.headerRowNum;
		const columnWidths = opts.columnWidths;
		if (titleRowNum) {
			ws.getRow(titleRowNum).eachCell(cell => {
				cell.font = { bold: true, size: 14 };
			});
		}
		if (tabTitleRowNum) {
			ws.getRow(tabTitleRowNum).eachCell(cell => {
				cell.font = { bold: true, italic: true, size: 14 };
			});
		}
		if (tabHeaderRowNum) {
			ws.getRow(tabHeaderRowNum).eachCell(cell => {
				cell.font = { bold: true, italic: true, size: 14 };
			});
		}
		if (headerRowNum) {
			ws.getRow(headerRowNum).eachCell(cell => {
				cell.font = { italic: true };
			});
		}
		if (columnWidths && columnWidths.length > 0) {
			columnWidths.forEach((w, i) => {
				ws.getColumn(i + 1).width = w;
			});
		}
	}

	async function tableExport () {
		// After a long cogitation, I've decided to export the contentin every situation, even if no content or no header found.
		
		// first get all contentcontainer in the page
		let containers = editor.querySelectorAll('contentcontainer');
		if (containers.length == 0) {
			console.log('tableExport - no containers found');
			return;
		}

		// prepare the workbook
		let wb = new ExcelJS.Workbook();
		const pageTitle = editor.querySelector('h1') != null ? editor.querySelector('h1').textContent.trim() : 'table';
		const hasHeaderTabs = editor.querySelector('headertabs') != null;
		let sharedColumnWidths = null;
		const usedSheetNames = new Set();
		
		// loop on containers
		containers.forEach(container => {
			// if number of containers is 1, the title of the workbook and of the sheet will be the title of the page.
			let sheetTitle;
			if (containers.length == 1) {
				sheetTitle = tableExportUniqueSheetName(pageTitle, null, usedSheetNames);
			} else {
				let headertab = editor.querySelector('headertab[key="' + container.getAttribute('key') + '"]');
				const headertabLabel = headertab != null ? headertab.innerText.trim() : 'Feuille ' + container.getAttribute('key');
				sheetTitle = tableExportUniqueSheetName(headertabLabel, container.getAttribute('key'), usedSheetNames);
			}
			let ws = wb.addWorksheet(sheetTitle);
			const titleRowNum = 1;
			ws.addRow([pageTitle]);
			let tabTitleRowNum = null;
			let tabHeaderRowNum = null;
			let headerRowNum = null;
			let nextRowNum = 2;
				
			// get headers (table mode)
			let header = container.querySelectorAll('headerrow > div');
			if (!(header.length > 0)) { 
				// Row display mode: infer headers from labels of first visible row.
				let firstVisibleRow = container.querySelector('row:not(.hidden)');
				if (firstVisibleRow) {
					header = tableExportGridRowCells(firstVisibleRow);
				}
			}
			if (!(header.length > 0)) { 
				header = false;
				console.log('tableExport - no header found in container ', container.id);
			}
			// get all selected rows
			let selectedRows = container.querySelectorAll('row:not(.hidden)');
			if (!(selectedRows.length > 0)) {
				console.log('tableExport - no content found in container ', container.id);
				selectedRows = false;
			}
			
			const tabTitle = tableExportTabTitle(container);
			if (tabTitle) {
				ws.addRow([tabTitle]);
				tabTitleRowNum = nextRowNum++;
			}

			const colCountForTabHeader = header ? header.length : (selectedRows && selectedRows.length > 0 ? tableExportRowCells(selectedRows[0]).length : 0);
			const tabHeaderContent = tableExportTabHeaderRow(container, colCountForTabHeader);
			if (tabHeaderContent) {
				ws.addRow(tabHeaderContent);
				tabHeaderRowNum = nextRowNum++;
			}

			// if headers, add them and use them to set cells
			let format = false;
			if (header) {
				let headerContent = [];
				format = [];
				header.forEach( h => {
					let title = tableExportCellLabel(h);
					let inputType = tableExportCellInputType(h);
					headerContent.push(title);
					format.push(inputType);
				})
				ws.addRow(headerContent);
				headerRowNum = nextRowNum++;
			}
	
			// work on now on rows
			if (selectedRows) {
				selectedRows.forEach( r => {
					let rc = [];
					let cells = tableExportRowCells(r);
					if (cells.length > 0) {
						cells.forEach(c => {
							rc.push(getExportCellValue(c));
						})
					}
					ws.addRow(rc);
				})
			}

			let columnWidths = [];
			if (hasHeaderTabs && sharedColumnWidths) {
				columnWidths = sharedColumnWidths.slice();
			} else {
				if (header && header.length > 0) {
					columnWidths = tableExportDomColumnWidths(header);
				} else if (selectedRows && selectedRows.length > 0) {
					columnWidths = tableExportDomColumnWidths(tableExportRowCells(selectedRows[0]));
				}
				const colCount = columnWidths.length;
				if (colCount > 0) {
					const firstDataRow = (headerRowNum || tabHeaderRowNum || tabTitleRowNum || titleRowNum) + 1;
					for (let c = 0; c < colCount; c++) {
						let maxChars = columnWidths[c];
						for (let r = firstDataRow; r <= ws.rowCount; r++) {
							const val = ws.getRow(r).getCell(c + 1).value;
							if (val != null) {
								maxChars = Math.max(maxChars, String(val).length + 2);
							}
						}
						columnWidths[c] = Math.min(60, maxChars);
					}
					columnWidths = columnWidths.map(w => Math.max(4, w / 2));
					if (hasHeaderTabs && !sharedColumnWidths) {
						sharedColumnWidths = columnWidths.slice();
					}
				}
			}
			const colCountForFmt = columnWidths.length;
			if (colCountForFmt > 0 && ws.rowCount > 0) {
				const firstDataRow = (headerRowNum || tabHeaderRowNum || tabTitleRowNum || titleRowNum) + 1;
				for (let c = 0; c < colCountForFmt; c++) {
					const colValues = [];
					for (let r = firstDataRow; r <= ws.rowCount; r++) {
						colValues.push(ws.getRow(r).getCell(c + 1).value);
					}
					const numFmt = tableExportNumFmtFromValues(colValues);
					if (numFmt) {
						ws.getColumn(c + 1).numFmt = numFmt;
					}
				}
			}
			tableExportApplySheetLayout(ws, { titleRowNum, tabTitleRowNum, tabHeaderRowNum, headerRowNum, columnWidths });
			
		})


		// Generate an Excel file as a buffer
        const buffer = await wb.xlsx.writeBuffer();

        // Create a Blob from the buffer
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });

        // Create a link element to trigger the download
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);

        // set a date (hours.minutes always two digits each, e.g. 21.01)
        let date = new Date();
        const pad2 = n => String(n).padStart(2, '0');
        let dateYmdHi = date.getDate() + '.' + (date.getMonth() + 1) + '.' + date.getFullYear() + '_' + pad2(date.getHours()) + '.' + pad2(date.getMinutes());


        link.download = pageTitle + '_' + dateYmdHi + '.xlsx';  

        // Programmatically click the link to trigger the download
        link.click();
	}
</script>
