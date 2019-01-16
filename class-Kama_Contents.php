<?php

/**
 * Содержание (оглавление) для больших постов.
 *
 * @author:  Kama
 * @info:    http://wp-kama.ru/?p=1513
 * @version: 3.17
 *
 * @changelog: https://github.com/doiftrue/Kama_Contents/blob/master/CHANGELOG.md
 */
class Kama_Contents {

	public $opt = [
		// Отступ слева у подразделов в px.
		'margin'     => 40,
		// Теги по умолчанию по котором будет строиться оглавление. Порядок имеет значение.
		// Кроме тегов, можно указать атрибут classа: array('h2','.class_name'). Можно указать строкой: 'h2 h3 .class_name'
		'selectors'  => [ 'h2','h3','h4' ],
		// Ссылка на возврат к оглавлению. '' - убрать ссылку
		'to_menu'    => 'к содержанию ↑',
		// Заголовок. '' - убрать заголовок
		'title'      => 'Содержание:',
		// Css стили. '' - убрать стили
		'css'        => '.kc__gotop{ display:block; text-align:right; }
						 .kc__title{ font-style:italic; padding:1em 0; }
						 .kc__anchlink{ color:#ddd!important; position:absolute; margin-left:-1em; }',
		// JS код (добавляется после HTML кода)
		'js'  => '',
		// Минимальное количество найденных тегов, чтобы оглавление выводилось.
		'min_found'  => 2,
		// Минимальная длина (символов) текста, чтобы оглавление выводилось.
		'min_length' => 2000,
		// Ссылка на страницу для которой собирается оглавление. Если оглавление выводиться на другой странице...
		'page_url'   => '',
		// Название шоткода
		'shortcode'  => 'contents',
		// Оставлять символы в анкорах
		'spec'       => '\'.+$*~=',
		// Какой тип анкора использовать: 'a' - <a name="anchor"></a> или 'id' -
		'anchor_type' => 'id',
		// Название атрибута тега из значения которого будет браться анкор (если этот атрибут есть у тега). Ставим '', чтобы отключить такую проверку...
		'anchor_attr_name' => 'id',
		// Включить микроразметку?
		'markup'      => false,
		// Добавить 'знак' перед подзаголовком статьи со ссылкой на текущий анкор заголовка. Укажите '#', '&' или что вам нравится :)
		'anchor_link' => '',
		// минимальное количество символов между заголовками содержания, для которых нужно выводить ссылку "к содержанию".
		// Не имеет смысла, если параметр 'to_menu' отключен. С целью производительности, кириллица считается без учета кодировки.
		// Поэтому 800 символов кириллицы - это примерно 1600 символов в этом параметре. 800 - расчет для сайтов на кириллице...
		'tomenu_simcount' => 800,
	];

	public $contents; // collect html (contents)

	private $temp;

	static $inst;

	function __construct( $args = array() ){
		$this->set_opt( $args );
		return $this;
	}

	/**
	 * Create instance.
	 *
	 * @param  array $args Options
	 * @return object Instance
	 */
	static function init( $args = array() ){
		is_null( self::$inst ) && self::$inst = new self( $args );
		if( $args ) self::$inst->set_opt( $args );
		return self::$inst;
	}

	function set_opt( $args = array() ){
		$this->opt = (object) array_merge( (array) $this->opt, (array) $args );
	}

	/**
	 * Processes the text, turns the shortcode in it into a table of contents.
	 *
	 * @param string $content      The text, which has a shortcode.
	 * @param string $contents_cb  Сallback function that will process the contents list.
	 *
	 * @return string Processed text with a table of contents, if it has a shotcode.
	 */
	function shortcode( $content, $contents_cb = '' ){
		if( false === strpos( $content, '['. $this->opt->shortcode ) )
			return $content;

		// get contents data
		if( ! preg_match('~^(.*)\['. $this->opt->shortcode .'([^\]]*)\](.*)$~s', $content, $m ) )
			return $content;

		$contents = $this->make_contents( $m[3], $m[2] );

		if( $contents && $contents_cb && is_callable($contents_cb) )
			$contents = $contents_cb( $contents );

		return $m[1] . $contents . $m[3];
	}

	/**
	 * Replaces the headings in the passed text (by ref), creates and returns a table of contents.
	 *
	 * @param string        $content The text from which you want to create a table of contents.
	 * @param array|string  $tags    Array of HTML tags to look for in the passed text.
	 *                               You can specify: tag names "h2 h3" or names of CSS classes ".foo .foo2".
	 *                               You can add "embed" mark here to get <ul> tag only (without header and wrapper block).
	 *                               It can be useful for use contents inside the text as a list.
	 *
	 * @return string HTML code of contents.
	 */
	function make_contents( & $content, $tags = '' ){
		// return if text is too short
		if( mb_strlen( strip_tags($content) ) < $this->opt->min_length )
			return '';

		$this->temp     = $this->opt;
		$this->contents = array();

		if( ! $tags )
			$tags = $this->opt->selectors;

		if( is_string($tags) ){
			$extra_tags = array();
			if( preg_match( '/(as_table)="([^"]+)"/', $tags, $mm ) ){
				$extra_tags[ $mm[1] ] = explode( '|', $mm[2] );
				$tags = str_replace( " {$mm[0]}", '', $tags ); // cut
			}
			$tags  = array_map( 'trim', preg_split('/[ ,]+/', $tags ) );
			$tags += $extra_tags;
		}

		$tags = array_filter( $tags ); // del empty

		// check tags
		foreach( $tags as $key => $tag ){

			// extra tags
			if( in_array( $key, array('as_table'), true ) ){
				$this->temp->as_table = $tag;

				unset( $tags[ $key ] );
				continue;
			}

			// remove special marker tags and set $args
			if( in_array( $tag, array('embed','no_to_menu') ) ){
				if( $tag == 'embed' )      $this->temp->embed = true;
				if( $tag == 'no_to_menu' ) $this->opt->to_menu = false;

				unset( $tags[ $key ] );
				continue;
			}

			// remove tag if it's not exists in content
			$patt = ( ($tag[0] == '.') ? 'class=[\'"][^\'"]*'. substr($tag, 1) : "<$tag" );
			if( ! preg_match("/$patt/i", $content ) ){
				unset( $tags[ $key ] );
				continue;
			}
		}

		if( ! $tags )
			return '';

		// set patterns from given $tags
		// separate classes & tags & set
		$class_patt = $tag_patt = $level_tags = array();
		foreach( $tags as $tag ){
			// class
			if( $tag{0} == '.' ){
				$tag  = substr( $tag, 1 );
				$link = & $class_patt;
			}
			// html tag
			else
				$link = & $tag_patt;

			$link[] = $tag;
			$level_tags[] = $tag;
		}

		$this->temp->level_tags = array_flip( $level_tags );

		// replace all titles & collect contents to $this->contents
		$patt_in = array();
		if( $tag_patt )   $patt_in[] = '(?:<('. implode('|', $tag_patt) .')([^>]*)>(.*?)<\/\1>)';
		if( $class_patt ) $patt_in[] = '(?:<([^ >]+) ([^>]*class=["\'][^>]*('. implode('|', $class_patt) .')[^>]*["\'][^>]*)>(.*?)<\/'. ($patt_in?'\4':'\1') .'>)';

		$patt_in = implode('|', $patt_in );

		$this->temp->content = $content;

		// collect and replace
		$_content = preg_replace_callback("/$patt_in/is", array( $this, '_make_contents_callback'), $content, -1, $count );

		if( ! $count || $count < $this->opt->min_found ){
			unset($this->temp); // clear cache
			return '';
		}

		$this->temp->content = $content = $_content; // $_content was for check reasone

		// html
		static $css, $js;
		$embed   = isset($this->temp->embed);
		$_tit    = & $this->opt->title;
		$_is_tit = ! $embed && $_tit;

		// markup
		$ItemList = $this->opt->markup ? ' itemscope itemtype="http://schema.org/ItemList"' : '';

		if( isset($this->temp->as_table) ){
			$contents = '
			<table class="contents" id="kcmenu"'. ($ItemList ?: '') .'>
				<thead>
					<tr>
						<th>'. esc_html( $this->temp->as_table[0] ) .'</th>
						<th>'. esc_html( $this->temp->as_table[1] ) .'</th>
					</tr>
				</thead>
				<tbody>
					'. implode('', $this->contents ) .'
				</tbody>
			</table>';
		}
		else {
			$contents =
				( $_is_tit ? '<div class="kc__wrap"'. $ItemList .' >' : '' ) .
				( $_is_tit ? '<span style="display:block;" class="kc-title kc__title" id="kcmenu"'. ($ItemList?' itemprop="name"':'') .'>'. $_tit .'</span>'. "\n" : '' ) .
				'<ul class="contents"'. ( (! $_tit || $embed) ? ' id="kcmenu"' : '' ) . ( ($ItemList && ! $_is_tit ) ? $ItemList : '' ) .'>'. "\n".
				implode('', $this->contents ) .
				'</ul>'."\n" .
				( $_is_tit ? '</div>' : '' );
		}

		$contents =
			( ( ! $css && $this->opt->css ) ? '<style>'. preg_replace('/[\n\t ]+/', ' ', $this->opt->css ) .'</style>' : '' ) .
			$contents .
			( ( ! $js && $this->opt->js ) ? '<script>'. preg_replace('/[\n\t ]+/', ' ', $this->opt->js ) .'</script>' : '' ) ;

		unset( $this->temp ); // clear cache

		return $this->contents = $contents;
	}

	## callback function to replace and collect contents
	private function _make_contents_callback( $match ){
		$temp = & $this->temp;

		// it's only class selector in pattern
		if( count($match) == 5 ){
			$tag   = $match[1];
			$attrs = $match[2];
			$tag_txt = $match[4];

			$level_tag = $match[3]; // class_name
		}
		// it's found tag selector
		elseif( count($match) == 4 ){
			$tag   = $match[1];
			$attrs = $match[2];
			$tag_txt = $match[3];

			$level_tag = $tag;
		}
		// it's found class selector
		else{
			$tag   = $match[4];
			$attrs = $match[5];
			$tag_txt = $match[7];

			$level_tag = $match[6]; // class_name
		}

		if( isset($this->temp->as_table) ){
			$tag_desc = '';
			//if( preg_match( '/'. preg_quote($match[0], '/') .'\s*<p>((?:.(?!<\/p>))+)./', $this->temp->content, $mm ) ){
			if( preg_match( '/'. preg_quote($match[0], '/') .'\s*<p>(.+?)<\/p>/', $this->temp->content, $mm ) ){
				$tag_desc = $mm[1];
			}
		}

		$opt = $this->opt; // short love

		// if tag contains id attribute it become anchor
		if( $opt->anchor_attr_name && preg_match('/ *('. preg_quote($opt->anchor_attr_name) .')=([\'"])(.+?)\2 */i', $attrs, $id_match) ){
			if( in_array($id_match[1], array('id','name')) )
				$attrs = str_replace( $id_match[0], '', $attrs ); // delete 'id' or 'name' attr from attrs
			$anchor = $this->_sanitaze_anchor( $id_match[3] );
		}
		else
			$anchor = $this->_sanitaze_anchor( $tag_txt );

		$level = @ $temp->level_tags[ $level_tag ];
		if( $level > 0 )
			$sub = ( $opt->margin ? ' style="margin-left:'. ($level*$opt->margin) .'px;"' : '') . ' class="sub sub_'. $level .'"';
		else
			$sub = ' class="top"';

		// collect contents
		// markup
		$_is_mark = $opt->markup;

		$temp->counter = empty($temp->counter) ? 1 : $temp->counter+1;

		// $tag_txt не может содержать A, IMG теги - удалим если надо...
		$cont_elem_txt = $tag_txt;
		if( false !== strpos($cont_elem_txt, '</a>') ) $cont_elem_txt = preg_replace('~<a[^>]+>|</a>~', '', $cont_elem_txt );
		if( false !== strpos($cont_elem_txt, '<img') ) $cont_elem_txt = preg_replace('~<img[^>]+>~', '', $cont_elem_txt );

		if( isset($this->temp->as_table) ){
			$this->contents[] = "\t".'
				<tr>
					<td '. ($_is_mark?' itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem"':'') .'>
						<a rel="nofollow"'. ($_is_mark?' itemprop="url"':'') .' href="'. $opt->page_url .'#'. $anchor .'">
							'.( $_is_mark ? '<span itemprop="name">'. $cont_elem_txt .'</span>' : $cont_elem_txt ).'
						</a>
						'.( $_is_mark ? ' <meta itemprop="position" content="'. $temp->counter .'" />':'' ).'
					</td>
					<td>'. $tag_desc .'</td>
				</tr>'. "\n";
		}
		else {
			$this->contents[] = "\t".'
				<li'. $sub . ($_is_mark?' itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem"':'') .'>
					<a rel="nofollow"'. ($_is_mark?' itemprop="url"':'') .' href="'. $opt->page_url .'#'. $anchor .'">
						'.( $_is_mark ? '<span itemprop="name">'. $cont_elem_txt .'</span>' : $cont_elem_txt ).'
					</a>
					'.( $_is_mark ? ' <meta itemprop="position" content="'. $temp->counter .'" />':'' ).'
				</li>'. "\n";
		}

		if( $opt->anchor_link )
			$tag_txt = '<a rel="nofollow" class="kc__anchlink" href="#'. $anchor .'">'. $opt->anchor_link .'</a> ' . $tag_txt;

		// anchor type: 'a' or 'id'
		if( $opt->anchor_type === 'a' )
			$new_el = '<a class="kc__anchor" name="'. $anchor .'"></a>'."\n<$tag $attrs>$tag_txt</$tag>";
		else
			$new_el = "\n<$tag id=\"$anchor\" $attrs>$tag_txt</$tag>";

		$to_menu = '';
		if( $opt->to_menu ){
			// go to contents
			$to_menu = '<a rel="nofollow" class="kc-gotop kc__gotop" href="'. $opt->page_url .'#kcmenu">'. $opt->to_menu .'</a>';

			// remove '$to_menu' if simbols beatween $to_menu too small (< 300)
			$pos = strpos( $temp->content, $match[0] ); // mb_strpos( $temp->content, $match[0] ) - в 150 раз медленнее!
			if( empty($temp->elpos) ){
				$prevpos = 0;
				$temp->elpos = array( $pos );
			}
			else {
				$prevpos = end($temp->elpos);
				$temp->elpos[] = $pos;
			}
			$simbols_count = $pos - $prevpos;
			if( $simbols_count < $opt->tomenu_simcount ) $to_menu = '';
		}

		return $to_menu . $new_el;
	}

	## anchor transliteration
	function _sanitaze_anchor( $anch ){
		$anch = strip_tags( $anch );

		$iso9 = array(
			'А'=>'A', 'Б'=>'B', 'В'=>'V', 'Г'=>'G', 'Д'=>'D', 'Е'=>'E', 'Ё'=>'YO', 'Ж'=>'ZH',
			'З'=>'Z', 'И'=>'I', 'Й'=>'J', 'К'=>'K', 'Л'=>'L', 'М'=>'M', 'Н'=>'N', 'О'=>'O',
			'П'=>'P', 'Р'=>'R', 'С'=>'S', 'Т'=>'T', 'У'=>'U', 'Ф'=>'F', 'Х'=>'H', 'Ц'=>'TS',
			'Ч'=>'CH', 'Ш'=>'SH', 'Щ'=>'SHH', 'Ъ'=>'', 'Ы'=>'Y', 'Ь'=>'', 'Э'=>'E', 'Ю'=>'YU', 'Я'=>'YA',
			// small
			'а'=>'a', 'б'=>'b', 'в'=>'v', 'г'=>'g', 'д'=>'d', 'е'=>'e', 'ё'=>'yo', 'ж'=>'zh',
			'з'=>'z', 'и'=>'i', 'й'=>'j', 'к'=>'k', 'л'=>'l', 'м'=>'m', 'н'=>'n', 'о'=>'o',
			'п'=>'p', 'р'=>'r', 'с'=>'s', 'т'=>'t', 'у'=>'u', 'ф'=>'f', 'х'=>'h', 'ц'=>'ts',
			'ч'=>'ch', 'ш'=>'sh', 'щ'=>'shh', 'ъ'=>'', 'ы'=>'y', 'ь'=>'', 'э'=>'e', 'ю'=>'yu', 'я'=>'ya',
			// other
			'Ѓ'=>'G', 'Ґ'=>'G', 'Є'=>'YE', 'Ѕ'=>'Z', 'Ј'=>'J', 'І'=>'I', 'Ї'=>'YI', 'Ќ'=>'K', 'Љ'=>'L', 'Њ'=>'N', 'Ў'=>'U', 'Џ'=>'DH',
			'ѓ'=>'g', 'ґ'=>'g', 'є'=>'ye', 'ѕ'=>'z', 'ј'=>'j', 'і'=>'i', 'ї'=>'yi', 'ќ'=>'k', 'љ'=>'l', 'њ'=>'n', 'ў'=>'u', 'џ'=>'dh'
		);

		$anch = strtr( $anch, $iso9 );

		$spec = preg_quote( $this->opt->spec );
		$anch = preg_replace("/[^a-zA-Z0-9_$spec\-]+/", '-', $anch ); // все ненужное на '-'
		$anch = strtolower( trim( $anch, '-') );
		$anch = substr( $anch, 0, 70 ); // shorten
		$anch = $this->_unique_anchor( $anch );

		return $anch;
	}

	## adds number at the end if this anchor already exists
	function _unique_anchor( $anch ){
		$temp = & $this->temp;

		// check and unique anchor
		if( empty($temp->anchors) ){
			$temp->anchors = array( $anch => 1 );
		}
		elseif( isset($temp->anchors[ $anch ]) ){
			$lastnum = substr( $anch, -1 );
			$lastnum = is_numeric($lastnum) ? $lastnum + 1 : 2;
			return $this->_unique_anchor( "$anch-$lastnum" );
		}
		else {
			$temp->anchors[ $anch ] = 1;
		}

		return $anch;
	}

	## cut the shortcode from the content
	function strip_shortcode( $text ){
		return preg_replace('~\['. $this->opt->shortcode .'[^\]]*\]~', '', $text );
	}

}

