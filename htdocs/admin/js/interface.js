function form_submit( form ) {
	for ( var name in CKEDITOR.instances )
		CKEDITOR.instances[name].updateElement();
	return CheckForm.validate( form );
}

function checkAllBoxes( checked ) {
	$('#table input:checkbox').attr('checked',checked);
}

function module_param_card() {
	var $param_type = $('#card select[name="param_type"]');
	var $param_default = $('#card input[name="param_default"]');
	var $param_table = $('#card select[name="param_table"]');
	var $param_require = $('#card input[name="param_require"]');
	
	var change_function = function() {
		var param_type = $param_type.val(); var param_require = $param_require.attr('checked');
		var no_default = param_type=='select' || param_type=='table' || param_type=='boolean' || param_type=='';
		
		$param_default.parents('tr:first').toggle( !no_default );
		$param_default.attr('errors', (!no_default && param_require ? 'require' : '')+'|'+(param_type=='int' ? 'int' : ''));
		if ( no_default ) $param_default.val('');
		
		$param_table.parents('tr:first').toggle( param_type=='table' );
		$param_table.attr('errors', param_type=='table' ? 'require' : '');
		if ( param_type!='table' ) $param_table.val('');
	};
	
	$param_type.change( change_function );
	$param_require.change( change_function ).change();
}

function block_card( param_list ) {
	var page_list = param_list.page_list;
	var area_list = param_list.area_list;
	
	var $block_page = $('#card select[name="block_page"]');
	var $block_area = $('#card select[name="block_area"]');
	
	$block_page.change( function() {
		var block_page = $block_page.val();
		var block_area = $block_area.val();
		
		var page_layout = '';
		for ( var page_index in page_list )
			if ( block_page == page_list[page_index].page_id )
				page_layout = page_list[page_index].page_layout;
		
		$('option', $block_area).remove();
		
		$block_area.append('<option value=""></option>');
		for ( var area_index in area_list )
			if ( page_layout == '' || page_layout == area_list[area_index].area_layout )
				$block_area.append('<option value="' + area_list[area_index].area_id + '"' + 
					( block_area == area_list[area_index].area_id ? ' selected="selected"' : '' ) + '>' +
						area_list[area_index].area_title + '</option>');
	}).change();
}

var CheckForm =
{
	// Массив обработчиков полей по умолчанию
	aCheckHandlers: {
		'require': { 'method': 'validate_nonempty', 'message': 'Не заполнено обязательное поле!' },
		'int': { 'method': 'validate_int', 'message': 'Неверный формат целого числа!' },
		'float': { 'method': 'validate_float', 'message': 'Неверный формат числа с плавающей точкой!' },
		'email': { 'method': 'validate_email', 'message': 'Неверный формат e-mail!' },
		'alpha': { 'method': 'validate_login', 'message': 'Неверный формат поля (строка из цифр или латинских букв без пробелов)!' },
		'dirname': { 'method': 'validate_dirname', 'message': 'Неверный формат названия директории!' },
		'date': { 'method': 'validate_date', 'message': 'Неверный формат даты (DD.MM.YYYY)!' },
		'time': { 'method': 'validate_time', 'message': 'Неверный формат времени (HH:MM)!' },
		'datetime': { 'method': 'validate_datetime', 'message': 'Неверный формат даты/времени (DD.MM.YYYY HH:MM)!' },
		'radio': { 'method': 'validate_radio', 'message': 'Не выбран ни один из вариантов!' },
		'radioalt': { 'method': 'validate_radioalt', 'message': 'Не выбран ни один из вариантов!' },
		'checkboxgroup': { 'method': 'validate_checkboxgroup', 'message': 'Не выбран ни один из вариантов!' },
		'checkboxgroupalt': { 'method': 'validate_checkboxgroupalt', 'message': 'Не выбран ни один из вариантов!' } },

	// Ссылка на объект текущей формы
	oForm: null,

	// Метод проверки правильности заполнения полей
	validate: function( oForm )
	{
		if ( !oForm ) return false;
		
		this.oForm = oForm;
		
		for ( var i = 0; i < this.oForm.elements.length; i++ )
		{
			var oItem = this.oForm.elements[i];
			var sErrors = oItem.getAttribute( 'errors' );
			if ( !sErrors ) continue;
			
			var aErrors = sErrors.split( '|' );
			if ( !aErrors.length ) continue;
			
			for ( var index in aErrors )
			{
				if ( !this.aCheckHandlers[aErrors[index]] ) continue;
				
				var sMethod = this.aCheckHandlers[aErrors[index]]['method'];
				if ( this[ sMethod ] && !this[ sMethod ]( oItem ) )
				{
					alert( this.aCheckHandlers[aErrors[index]]['message'] );
					try { oItem.focus() } catch (e) {};
					return false;
				}
			}
		}
		
		return this.validate_ext();
	},

	// Проверка на заполнение обязательного поля
	validate_nonempty: function( oItem )
	{
		if ( oItem.type == 'checkbox' )
			return oItem.checked;
		else if ( this.oForm[oItem.name + '_file'] && this.oForm[oItem.name + '_file'].type == 'file' )
			return oItem.value.replace( /(^\s*)|(\s*$)/g, '' ) != '' ||
				this.oForm[oItem.name + '_file'].value.replace( /(^\s*)|(\s*$)/g, '' ) != '';
		else
			return oItem.value.replace( /(^\s*)|(\s*$)/g, '' ) != '';
	},

	// Проверка на целое число
	validate_int: function( oItem )
	{
		return ( oItem.value == '' ) || /^\-?\+?\d+$/.test( oItem.value );
	},

	// Проверка на число с плавающей точкой
	validate_float: function( oItem )
	{
		return ( oItem.value == '' ) || /^\-?\+?\d+[\.,]?\d*$/.test( oItem.value );
	},

	// Проверка на e-mail
	validate_email: function( oItem )
	{
		return ( oItem.value == '' ) || /^[\w\.-]+@[\w\.-]+\.\w\w+$/.test( oItem.value );
	},

	// Проверка на логин
	validate_login: function( oItem )
	{
		return ( oItem.value == '' ) || /^\w+$/.test( oItem.value );
	},

	// Проверка на название директории
	validate_dirname: function( oItem )
	{
		return ( oItem.value == '' ) || /^[\w\.\[\]-]+$/.test( oItem.value );
	},

	// Проверка на дату
	validate_date: function( oItem )
	{
		if ( oItem.value == '' ) return true;
		
		var aMatches = oItem.value.match( /^(\d{2})\.(\d{2})\.(\d{4})$/ );
		if ( !aMatches ) return false;
		
		return this.check_date( aMatches[3], aMatches[2] - 1, aMatches[1] );
	},

	// Проверка на время
	validate_time: function( oItem )
	{
		if ( oItem.value == '' ) return true;
		
		var aMatches = oItem.value.match( /^(\d{2})\:(\d{2})$/ );
		if ( !aMatches ) return false;
		
		return this.check_time( aMatches[1], aMatches[2] );
	},

	// Проверка на дату/время
	validate_datetime: function( oItem )
	{
		if ( oItem.value == '' ) return true;
		
		var aMatches = oItem.value.match( /^(\d{2})\.(\d{2})\.(\d{4}) (\d{2})\:(\d{2})$/ );
		if ( !aMatches ) return false;
		
		return this.check_date( aMatches[3], aMatches[2] - 1, aMatches[1] ) && this.check_time( aMatches[4], aMatches[5] );
	},

	// Вспомогательный метод проверки корректности даты
	check_date: function( sYear, sMonth, sDate )
	{
		var dTempDate = new Date( sYear, sMonth, sDate );
		var bValid =
			( dTempDate.getFullYear() == sYear ) &&
			( dTempDate.getMonth() == sMonth ) &&
			( dTempDate.getDate() == sDate );
		return bValid;
	},

	// Вспомогательный метод проверки корректности времени
	check_time: function( sHour, sMinutes )
	{
		var bValid =
			( sHour >= 0 && sHour <= 23 ) &&
			( sMinutes >= 0 && sMinutes <= 59 );
		return bValid;
	},

	// Проверка чека группы радио-баттонов
	validate_radio: function( oItem )
	{
		var aItems = this.oForm[oItem.name].length ?
			this.oForm[oItem.name] : [ this.oForm[oItem.name] ];
		for ( var i = 0; i < aItems.length; i++ )
			if ( aItems[i].checked )
				return true;
		return false;
	},

	// Проверка чека группы радио-баттонов с альтернативой
	validate_radioalt: function( oItem )
	{
		var aItems = this.oForm[oItem.name].length ?
			this.oForm[oItem.name] : [ this.oForm[oItem.name] ];
		for ( var i = 0; i < aItems.length; i++ )
			if ( aItems[i].checked ) {
				if ( aItems[i].value != '_alt_' )
					return true;
				else if ( this.oForm['alt_' + oItem.name].value.replace( /(^\s*)|(\s*$)/g, '' ) != '' )
					return true;
			}
		return false;
	},

	// Проверка чека группы чекбоксов
	validate_checkboxgroup: function( oItem )
	{
		var aItems = this.oForm[oItem.name].length ?
			this.oForm[oItem.name] : [ this.oForm[oItem.name] ];
		for ( var i = 0; i < aItems.length; i++ )
			if ( aItems[i].checked )
				return true;
		return false;
	},

	// Проверка чека группы чекбоксов с альтернативой
	validate_checkboxgroupalt: function( oItem )
	{
		var aItems = this.oForm[oItem.name].length ?
			this.oForm[oItem.name] : [ this.oForm[oItem.name] ];
		for ( var i = 0; i < aItems.length; i++ )
			if ( aItems[i].checked ) {
				if ( aItems[i].value != '_alt_' )
					return true;
				else if ( this.oForm['alt_' + oItem.name].value.replace( /(^\s*)|(\s*$)/g, '' ) != '' )
					return true;
			}
		return false;
	},

	// Дополнительный метод, переопределямый для расширения функционала
	validate_ext: function()
	{
		return true;
	}
};

/*
	Пример добавления нового обработчика:

	Dictionary.aWords['lang_check_prefix'] = 'Сообщение при ошибке';

	CheckForm.aCheckHandlers[ '_prefix_' ] =
	{
		'method': 'validate_method',
		'message': Dictionary.translate( 'lang_check_prefix' )
	};

	CheckForm[ 'validate_method' ] = function( oItem )
	{
		[code]
	};
*/

/*
	Календарь
	
	Пример добавления календаря на страницу
	
	<link rel="stylesheet" type="text/css" href="calendar.css"/>
	<script type="text/javascript" src="calendar.js"></script>
	...
	<form action="..." method="..." id="form_name">
		<input type="input" value="01.01.1970 00:00:00" name="field_name"/>
		<a href="" onclick="Calendar.show( document.forms['form_name']['field_name'], this, 'full' ); return false">...</a>
	</form>
*/
 
var Calendar =
{
	// Массив с названиями месяцев
	monthNames: [ 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь' ],
	
	// Массив с названиями дней недели
	weekNames: [ 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс' ],
	
	//
	// Показ календаря
	//
	show: function( control, source, mode )
	{
		this.mode = mode;
		this.control = control;
		
		// Получаем внутренный объект для работы с датами
		this.internalDate = Calendar.getInternalDate( this.mode );
		
		// Забираем дату из поля ввода и парсим ее
		try {
			this.currentDate = this.internalDate.toDate( this.control.value );
		} catch ( e ) {
			alert( e ); this.currentDate = new Date();
		}
		
		// В первый раз создаем div календаря, задаем ему стиль
		if ( !this.divCalendar )
		{
			this.divCalendar = document.createElement('div');
			this.divCalendar.className = 'calendar';
			document.body.appendChild( this.divCalendar );
		}
		
		// Заполняем календарь таблицей чисел текущего месяца
		this.display( this.currentDate );
		
		// Определяем абсоблютные координаты опорных элементов
		var rControl = this.getPosition( control );
		var rSource = this.getPosition( source );
		
		// Позиционируем календарь в зависимости от его положения на странице
		var expand_right = rControl.x + this.divCalendar.offsetWidth <
			this.getClientWidth() + this.getBodyScrollLeft();
		var expand_bottom = rControl.y + control.offsetHeight + this.divCalendar.offsetHeight <
			this.getClientHeight() + this.getBodyScrollTop();
		
		if ( expand_right )
			this.divCalendar.style.left = rControl.x + 'px';
		else
			this.divCalendar.style.left = ( rSource.x + source.offsetWidth - this.divCalendar.offsetWidth ) + 'px';
		
		if ( expand_bottom )
			this.divCalendar.style.top = ( rControl.y + control.offsetHeight + 4 ) + 'px';
		else
			this.divCalendar.style.top = ( rControl.y - this.divCalendar.offsetHeight - 4 ) + 'px';
		
		// Показываем календарь
		this.divCalendar.style.visibility = 'visible';
	},
	
	//
	// Установка текущей даты
	//
	now: function( control, mode )
	{
		var internalDate = Calendar.getInternalDate( mode );
		
		control.value = internalDate.toString( new Date() );
	},
	
	// Метод возвращает внутренный объект для работы с датами
	getInternalDate: function( mode )
	{
		if ( mode == 'long' )
			return new DateLong();
		else if ( mode == 'full' )
			return new DateFull();
		else
			return new DateShort();
	},
	
	// Метод возвращает абсолютные координаты объекта
	getPosition: function( oObj )
	{
		var x = oObj.offsetLeft, y = oObj.offsetTop;
		while ( oObj = oObj.offsetParent )
			if ( oObj.tagName != 'HTML' )
				x += oObj.offsetLeft, y += oObj.offsetTop;
		
		return { 'x': x, 'y': y };
	},
	
	//
	// Метод возвращает проскролленность страницы по горизонтали
	//
	getBodyScrollLeft: function()
	{
		return ( document.documentElement && document.documentElement.scrollLeft ) ||
			( document.body && document.body.scrollLeft );
	},
	
	//
	// Метод возвращает проскролленность страницы по вертикали
	//
	getBodyScrollTop: function()
	{
		return ( document.documentElement && document.documentElement.scrollTop ) ||
			( document.body && document.body.scrollTop );
	},
	
	//
	// Метод возвращает ширину клиентской части окна
	//
	getClientWidth: function()
	{
		return ( !window.opera && document.documentElement && document.documentElement.clientWidth ) ||
			( document.body && document.body.clientWidth );
	},
	
	//
	// Метод возвращает высоту клиентской части окна
	//
	getClientHeight: function()
	{
		return ( !window.opera && document.documentElement && document.documentElement.clientHeight ) ||
			( document.body && document.body.clientHeight );
	},
	
	//
	// Скрытие календаря
	//
	hide: function()
	{
		this.divCalendar.style.visibility = 'hidden';
	},
	
	//
	// Заполнение поля ввода выбранной датой. Скрытие календаря
	//
	writeDate: function( date )
	{
		this.displayDate.setDate( date );
		
		this.control.value = this.internalDate.toString( this.displayDate );
		
		this.hide();
	},
	
	//
	// Смена месяца
	//
	setMonth: function(select)
	{
		var tDate =
			new Date(
				this.displayDate.getFullYear(), select.selectedIndex, 1,
				this.displayDate.getHours(), this.displayDate.getMinutes(), this.displayDate.getSeconds() );
		this.display( tDate );
	},
	
	//
	// Смена года
	//
	setYear: function( year )
	{
		if ( parseInt( year, 10 ) != year )
			return;
		
		var tDate =
			new Date(
				year, this.displayDate.getMonth(), 1,
				this.displayDate.getHours(), this.displayDate.getMinutes(), this.displayDate.getSeconds() );
		this.display( tDate );
	},
	
	// Смена года стрелками
	changeYear: function( shift )
	{
		var tDate =
			new Date(
				this.displayDate.getFullYear() + shift, this.displayDate.getMonth(), 1,
				this.displayDate.getHours(), this.displayDate.getMinutes(), this.displayDate.getSeconds() );
		this.display( tDate );
	},
	
	//
	// Смена часа
	//
	setHours: function( hours )
	{
		this.displayDate.setHours( hours );
	},
	
	//
	// Смена минут
	//
	setMinutes: function( minutes )
	{
		this.displayDate.setMinutes( minutes );
	},
	
	//
	// Смена секунд
	//
	setSeconds: function( seconds )
	{
		this.displayDate.setSeconds( seconds );
	},
	
	//
	// Сравнение дат
	//
	isEqualDate: function( dDate1, dDate2 )
	{
		return	( dDate1.getFullYear() == dDate2.getFullYear() ) &&
				( dDate1.getMonth() == dDate2.getMonth() ) &&
				( dDate1.getDate() == dDate2.getDate() );
	},
	
	//
	// Заполнение таблицы календаря числами текущего месяца
	//
	display: function( oDate )
	{
		// Отображаемая в данный момент дата
		this.displayDate = oDate;
		
		var year  = this.displayDate.getFullYear();
		var month = this.displayDate.getMonth();
		
		var hours  = this.displayDate.getHours();
		var minutes = this.displayDate.getMinutes();
		var seconds = this.displayDate.getSeconds();
		
		var text = '';
		
		// Шапка календаря (месяц, год, кнопки смены года, кнопка закрытия)
		text += '	<table class="header">';
		text += '		<tr>';
		text += '			<td rowspan="2" align="center">';
		text += '				<select class="month" onchange="Calendar.setMonth( this )">';
		for ( i = 0; i < this.monthNames.length; i++ )
			text += '					<option value="' + i + '"' + ( ( i == month ) ? ' selected="selected"' : '' ) + '>' + this.monthNames[i] + '</option>';
		text += '				</select>';
		text += '			</td>';
		text += '			<td rowspan="2" align="right">';
		text += '				<input type="text" class="year" value="' + year + '" onchange="Calendar.setYear( this.value )"/>';
		text += '			</td>';
		text += '			<td class="year-up" onmousedown="Calendar.changeYear(1)"/>';
		text += '			<td rowspan="2" class="close" onmousedown="Calendar.hide()"/>';
		text += '		</tr>';
		text += '		<tr>';
		text += '			<td class="year-down" onmousedown="Calendar.changeYear(-1)"/>';
		text += '		</tr>';
		text += '	</table>';
		
		// Тело календаря (дни недели, числа текущегно месяца)
		text += '	<table class="date">';
		text += '		<tr>';
		for ( i = 0; i < this.weekNames.length; i++ )
			text += '			<td class="weekdays" style="width: 14%">' + this.weekNames[i] + '</td>';
		text += '		</tr>';
		
		// Определение дня недели первого числа месяца
		var firstDayInstance = new Date( year, month, 1 )
		var firstDay = firstDayInstance.getDay();
		if (firstDay == 0) firstDay = 7;
		
		// Определение числа дней в текущем месяце
		var lastDateInstance = new Date( year, month + 1, 0 );
		var lastDate = lastDateInstance.getDate();
		
		var day = 1; var curCell = 1; 
		var displayDay = ''; var tDate = null;
		
		for ( row = 0; row < Math.ceil( ( lastDate + firstDay - 1 ) / 7 ); row++ )
		{
			text += '		<tr>';
			for ( col = 0; col < 7; col++ )
			{
				// Пропускаем дни до первого числа месяца
				if ( curCell < firstDay )
				{
					text += '			<td>&nbsp;</td>'; curCell++;
				}
				// Пропускаем дни после последнего числа месяца
				else if ( day > lastDate )
				{
					text += '			<td>&nbsp;</td>';
				}
				else 
				{
					sLink = 'javascript:Calendar.writeDate(' + day + ')';
					
					// Установленная дата
					if ( this.isEqualDate( this.currentDate, new Date( year, month, day ) ) )
						text += '			<td class="today"><a class="today" href="' + sLink + '">' + day + '</a></td>';
					// Выходные дни
					else if ( col > 4 )
						text += '			<td class="weekend"><a class="weekend" href="' + sLink + '">' + day + '</a></td>';
					// Обычные дни
					else
						text += '			<td><a href="' + sLink + '">' + day + '</a></td>';
					
					day++;
				}
			}
			text += '		</tr>';
		}
		text += '	</table>';
		
		// Подвал календаря (время)
		if ( this.mode == 'long' || this.mode == 'full' )
		{
			// Часы и минуты
			text += '	<table class="footer">';
			text += '		<tr>';
			text += '			<td align="center">';
			
			text += '				<table class="time">';
			text += '					<tr>';
			text += '						<td class="time_select">';
			text += '							<select class="time" onchange="Calendar.setHours(this.value)">';
			for ( i = 0; i < 24; i++ )
				text += '								<option value="' + i + '"' + ( ( i == hours ) ? ' selected="selected"' : '' ) + '>' + lpad( i, 2, '0' ) + '</option>';
			text += '							</select>';
			text += '						</td>';
			text += '						<td class="time_separator"/>';
			text += '						<td class="time_select">';
			text += '							<select class="time" onchange="Calendar.setMinutes(this.value)">';
			for ( i = 0; i < 60; i++ )
				text += '								<option value="' + i + '"' + ( ( i == minutes ) ? ' selected="selected"' : '' ) + '>' + lpad( i, 2, '0' ) + '</option>';
			text += '							</select>';
			text += '						</td>';
			
			// Секунды
			if ( this.mode == 'full' )
			{
				text += '						<td class="time_separator"/>';
				text += '						<td class="time_select">';
				text += '							<select class="time" onchange="Calendar.setSeconds(this.value)">';
				for ( i = 0; i < 60; i++ )
					text += '								<option value="' + i + '"' + ( ( i == seconds ) ? ' selected="selected"' : '' ) + '>' + lpad( i, 2, '0' ) + '</option>';
				text += '							</select>';
				text += '						</td>';
			}
			
			text += '					</tr>';
			text += '				</table>';
			
			text += '			</td>';
			text += '		</tr>';
			text += '	</table>';
		}
		
		this.divCalendar.innerHTML = text;
	}
}

// Объект для работы с датами формата DD.MM.YY (режим 'short', по умолчанию)
function DateShort()
{
	// Метод преобразуем строку в объект типа Date
	this.toDate = function( sDate )
	{
		if ( !sDate ) return new Date();
		
		var aMatch = sDate.match( /^(\d{2})\.(\d{2})\.(\d{4})/ );
		if ( !aMatch )
			throw 'Неверный формат даты (DD.MM.YYYY)!';
		
		return new Date( aMatch[3], aMatch[2] - 1, aMatch[1] );
	}
	
	// Метод преобразуем объект типа Date в строку
	this.toString = function( oDate )
	{
		return '' +
			lpad( oDate.getDate(), 2, '0' ) + '.' +
			lpad( oDate.getMonth() + 1, 2, '0' ) + '.' +
			lpad( oDate.getFullYear(), 4, '0' );
	}
}

// Объект для работы с датами формата DD.MM.YY HH:MM (режим 'long')
function DateLong()
{
	// Метод преобразуем строку в объект типа Date
	this.toDate = function( sDate )
	{
		if ( !sDate ) return new Date();
		
		var aMatch = sDate.match( /^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2})\:(\d{2})/ );
		
		if ( !aMatch )
			throw 'Неверный формат даты/времени (DD.MM.YYYY HH:MM)!';
		
		return new Date( aMatch[3], aMatch[2] - 1, aMatch[1], aMatch[4], aMatch[5] );
	}
	
	// Метод преобразуем объект типа Date в строку
	this.toString = function( oDate )
	{
		return '' +
			lpad( oDate.getDate(), 2, '0' ) + '.' +
			lpad( oDate.getMonth() + 1, 2, '0' ) + '.' +
			lpad( oDate.getFullYear(), 4, '0' ) + ' ' +
			lpad( oDate.getHours(), 2, '0' ) + ':' +
			lpad( oDate.getMinutes(), 2, '0' );
	}
}

// Объект для работы с датами формата DD.MM.YY HH:MM:SS (режим 'full')
function DateFull()
{
	// Метод преобразуем строку в объект типа Date
	this.toDate = function( sDate )
	{
		if ( !sDate ) return new Date();
		
		var aMatch = sDate.match( /^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2})\:(\d{2})\:(\d{2})/ );
		if ( !aMatch )
			throw 'Неверный формат даты/времени (DD.MM.YYYY HH:MM:SS)!';
		
		return new Date( aMatch[3], aMatch[2] - 1, aMatch[1], aMatch[4], aMatch[5], aMatch[6] );
	}
	
	// Метод преобразуем объект типа Date в строку
	this.toString = function( oDate )
	{
		return '' +
			lpad( oDate.getDate(), 2, '0' ) + '.' +
			lpad( oDate.getMonth() + 1, 2, '0' ) + '.' +
			lpad( oDate.getFullYear(), 4, '0' ) + ' ' +
			lpad( oDate.getHours(), 2, '0' ) + ':' +
			lpad( oDate.getMinutes(), 2, '0' ) + ':' +
			lpad( oDate.getSeconds(), 2, '0' );
	}
}

// Метод дополняет строку другой строкой до заданной длины слева
function lpad( sText, iLength, sSpace )
{
	var sResult = sText;
	for ( var i = 0; i < iLength - sText.toString().length; i++ )
		sResult = sSpace + sResult;
    return sResult;
}
