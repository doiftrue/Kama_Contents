<?php
/**
 * Содержание (оглавление) для больших постов.
 *
 * @author:  Kama
 * @info:    http://wp-kama.ru/?p=1513
 * @version: 4.3.1
 *
 * @changelog: https://github.com/doiftrue/Kama_Contents/blob/master/CHANGELOG.md
 */
class Kama_Contents {

	public $opt = [
		'margin'           => '2em',
		'selectors'        => [ 'h2', 'h3', 'h4' ],
		'to_menu'          => 'к оглавлению ↑',
		'title'            => 'Оглавление:',
		'css'              => '
			.kamatoc-wrap{ clear:both; }
			.kamatoc-wrap__title{ display:block; font-style:italic; padding:1em 0; }
			.kamatoc-gotop{ display:block; text-align:right; }
			.kamatoc-anchlink{ color:#ddd!important; position:absolute; margin-left:-1em; }
		',
		'js'               => '',
		'min_found'        => 2,
		'min_length'       => 2000,
		'page_url'         => '',
		'shortcode'        => 'contents',
		'spec'             => '',
		'anchor_type'      => 'id',
		'anchor_attr_name' => 'id',
		'markup'           => false,
		'anchor_link'      => '',
		'tomenu_simcount'  => 800,
	];

	/**
	 * Collects html (the contents).
	 *
	 * @var
	 */
	public $contents;

	private $temp;

	/**
	 * @var Kama_Contents
	 */
	public static $inst;

	/**
	 * Create instance.
	 *
	 * @param array $args {
	 *     Parameters.
	 *
	 *     @type string       $margin            Отступ слева у подразделов в px|em|rem.
	 *     @type string|array $selectors         HTML теги по котором будет строиться оглавление: 'h2 h3 h4'.
	 *                                           Порядок определяет уровень вложености.
	 *                                           Можно указать строку или массив: [ 'h2', 'h3', 'h4' ] или 'h2 h3 h4'.
	 *                                           Можно указать атрибут class: 'h2 .class_name'.
	 *                                           Если нужно чтобы разные теги были на одном уровне,
	 *                                           указываем их через |: 'h2|dt h3' или [ 'h2|dt', 'h3' ].
	 *     @type string       $to_menu           Ссылка на возврат к оглавлению. '' - убрать ссылку.
	 *     @type string       $title             Заголовок. '' - убрать заголовок.
	 *     @type string       $css               Css стили. '' - убрать стили.
	 *     @type string       $js                JS код (добавляется после HTML кода)
	 *     @type int          $min_found         Минимальное количество найденных тегов, чтобы оглавление выводилось.
	 *     @type int          $min_length        Минимальная длина (символов) текста, чтобы оглавление выводилось.
	 *     @type string       $page_url          Ссылка на страницу для которой собирается оглавление.
	 *                                           Если оглавление выводиться на другой странице...
	 *     @type string       $shortcode         Название шоткода.
	 *     @type string       $spec              Оставлять символы в анкорах. For example: `'.+$*=`.
	 *     @type string       $anchor_type       Какой тип анкора использовать: 'a' - `<a name="anchor"></a>` или 'id'.
	 *     @type string       $anchor_attr_name  Название атрибута тега из значения которого будет браться
	 *                                           анкор (если этот атрибут есть у тега). Ставим '', чтобы отключить такую проверку...
	 *     @type bool         $markup            Включить микроразметку?
	 *     @type string       $anchor_link       Добавить 'знак' перед подзаголовком статьи со ссылкой
	 *                                           на текущий анкор заголовка. Укажите '#', '&' или что вам нравится.
	 *     @type int          $tomenu_simcount   Минимальное количество символов между заголовками содержания,
	 *                                           для которых нужно выводить ссылку "к содержанию".
	 *                                           Не имеет смысла, если параметр 'to_menu' отключен. С целью производительности,
	 *                                           кириллица считается без учета кодировки. Поэтому 800 символов кириллицы -
	 *                                           это примерно 1600 символов в этом параметре. 800 - расчет для сайтов на кириллице.
	 * }
	 *
	 * @return Kama_Contents
	 */
	public static function init( $args = [] ){

		is_null( self::$inst ) && self::$inst = new self( $args );

		$args && self::$inst->set_opt( $args );

		return self::$inst;
	}

	public function __construct( $args = [] ){
		$this->set_opt( $args );
	}

	public function set_opt( $args = [] ){
		$this->opt = (object) array_merge( (array) $this->opt, (array) $args );
	}

	/**
	 * Cut the kamaTOC shortcode from the content.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public function strip_shortcode( $text ){
		return preg_replace( '~\[' . $this->opt->shortcode . '[^\]]*\]~', '', $text );
	}

	/**
	 * Processes the text, turns the shortcode in it into a table of contents.
	 * Use shortcode [contents] or [[contents]] to show shortcode as it is.
	 *
	 * @param string $content      The text, which has a shortcode.
	 * @param string $contents_cb  Сallback function that will process the contents list.
	 *
	 * @return string Processed text with a table of contents, if it has a shotcode.
	 */
	public function shortcode( $content, $contents_cb = '' ){

		$shortcode = $this->opt->shortcode;

		if( false === strpos( $content, "[$shortcode" ) ){
			return $content;
		}

		// get contents data
		// use `[[contents` to escape the shortcode
		if( ! preg_match( "/^(.*)(?<!\[)\[$shortcode([^\]]*)\](.*)$/su", $content, $m ) ){
			return $content;
		}

		$contents = $this->make_contents( $m[3], $m[2] );

		if( $contents && $contents_cb && is_callable( $contents_cb ) ){
			$contents = $contents_cb( $contents );
		}

		return $m[1] . $contents . $m[3];
	}

	/**
	 * Replaces the headings in the passed text (by ref), creates and returns a table of contents.
	 *
	 * @param string       $content  The text from which you want to create a table of contents.
	 * @param array|string $tags     Array of HTML tags to look for in the passed text.
	 *                               You can specify: tag names "h2 h3" or names of CSS classes ".foo .foo2".
	 *                               You can add "embed" mark here to get <ul> tag only (without header and wrapper block).
	 *                               It can be useful for use contents inside the text as a list.
	 *
	 * @return string table of contents HTML code.
	 */
	public function make_contents( string & $content, $tags = '' ){

		// text is too short
		if( mb_strlen( strip_tags( $content ) ) < $this->opt->min_length ){
			return '';
		}

		$this->temp = $this->opt;
		$this->contents = array();

		if( ! $tags ){
			$tags = $this->opt->selectors;
		}

		$this->temp->original_tags = $tags;

		// prase tags as string
		if( is_string( $tags ) ){
			$extra_tags = [];

			if( preg_match( '/(as_table)="([^"]+)"/', $tags, $mm ) ){

				$extra_tags[ $mm[1] ] = explode( '|', $mm[2] );
				$tags = str_replace( " $mm[0]", '', $tags ); // cut
			}

			$tags = array_map( 'trim', preg_split( '/[ ,|]+/', $tags ) );

			$tags += $extra_tags;
		}

		$tags = array_filter( $tags );

		// get parameters from tags
		foreach( $tags as $key => $tag ){

			// extra tags
			if( 'as_table' === $key ){
				$this->temp->as_table = $tag;

				unset( $tags[ $key ] );
			}
			elseif( 'embed' === $tag ){
				$this->temp->embed = true;

				unset( $tags[ $key ] );
			}
			elseif( 'no_to_menu' === $tag ){
				$this->opt->to_menu = false;

				unset( $tags[ $key ] );
			}
		}

		// remove tag if it's not exists in content
		foreach( $tags as $key => $tag ){

			$patt = ( $tag[0] === '.' )
				? 'class=[\'"][^\'"]*' . substr( $tag, 1 )
				: "<$tag";

			if( ! preg_match( "/$patt/i", $content ) ){
				unset( $tags[ $key ] );
			}
		}

		if( ! $tags ){
			return '';
		}

		// PREPARE TAGS ---
		$this->collect_contents( $content, $tags );

		// HTML ---

		$embed = isset( $this->temp->embed );
		$title = & $this->opt->title;
		$is_title = ! $embed && $title;

		// markup
		$ItemList = $this->opt->markup ? ' itemscope itemtype="https://schema.org/ItemList"' : '';
		$ItemName = $this->opt->markup ? '<meta itemprop="name" content="'. esc_attr( wp_strip_all_tags( $title ) ) .'" />' : '';

		if( isset( $this->temp->as_table ) ){

			$contents = '
			<table class="kamatoc" id="tocmenu"'. $ItemList .'>
				'. $ItemName .'
				<thead>
					<tr>
						<th>'. esc_html( $this->temp->as_table[0] ) .'</th>
						<th>'. esc_html( $this->temp->as_table[1] ) .'</th>
					</tr>
				</thead>
				<tbody>
					'. implode( '', $this->contents ) .'
				</tbody>
			</table>';
		}
		else {

			if( $is_title ){
				$contents_wrap_patt = '
					<div class="kamatoc-wrap">
						<span class="kamatoc-wrap__title">' . $title . '</span>
						%s
					</div>
				';
			}
			else{
				$contents_wrap_patt = '%s';
			}

			$contents = '
				<ul class="kamatoc" id="tocmenu" '. $ItemList .'>
					'. $ItemName .'
					'. implode( '', $this->contents ) .'
				</ul>';

			$contents = sprintf( $contents_wrap_patt, $contents );
		}

		$css_code = $js_code = '';

		if( $this->opt->css ){
			$css_code = '<style>' . preg_replace( '/[\n\t ]+/', ' ', $this->opt->css ) . '</style>';
		}

		if( $this->opt->js ){
			$js_code = '<script>' . preg_replace( '/[\n\t ]+/', ' ', $this->opt->js ) . '</script>';
		}

		$this->contents = $css_code . $contents . $js_code;

		unset( $this->temp ); // clear cache

		return $this->contents;
	}

	private function collect_contents( string & $content, array $tags ): void {

		// group HTML classes & tags for regex patterns
		$class_patt = $tag_patt = [];
		// collect levels
		$level_tags = [];
		foreach( $tags as $tag ){
			// class
			if( $tag[0] === '.' ){
				$tag  = substr( $tag, 1 );
				$_link = & $class_patt;
			}
			// html tag
			else{
				$_link = & $tag_patt;
			}

			$_link[] = $tag;
			$level_tags[] = $tag;
		}

		$level_tags = array_flip( $level_tags );

		// fix levels if it's not start from zero
		if( reset( $level_tags ) !== 0 ){
			while( reset( $level_tags ) !== 0 ){
				$level_tags = array_map( static function( $val ){ return $val - 1; }, $level_tags );
			}
		}

		// set equal level if tags specified with tag1|tag2
		$_prev_tag = '';
		foreach( $level_tags as $tag => $lvl ){

			if( $_prev_tag && false !== strpos( $this->temp->original_tags, "$_prev_tag|$tag" ) ){
				$level_tags[ $tag ] = $_prev_lvl;
			}

			$_prev_tag = $tag;
			$_prev_lvl = $lvl;
		}

		// set the levels one by one if they were broken after the last operation
		$_prev_lvl = 0;
		foreach( $level_tags as & $lvl ){

			// fix next lvl - it's wrong
			if( ! in_array( $lvl, [ $_prev_lvl, $_prev_lvl + 1 ], true ) ){

				$lvl = $_prev_lvl + 1;
			}

			$_prev_lvl = $lvl;
		}
		unset( $lvl );

		$this->temp->level_tags = $level_tags;

		// COLLECT CONTENTS ---

		// replace all titles & collect contents to $this->contents

		$patt_in = [];

		if( $tag_patt ){
			$patt_in[] = '(?:<(' . implode( '|', $tag_patt ) . ')([^>]*)>(.*?)<\/\1>)';
		}

		if( $class_patt ){
			$patt_in[] = '(?:<([^ >]+) ([^>]*class=["\'][^>]*(' . implode( '|', $class_patt ) . ')[^>]*["\'][^>]*)>(.*?)<\/' . ( $patt_in ? '\4' : '\1' ) . '>)';
		}

		$patt_in = implode( '|', $patt_in );

		$this->temp->content = $content;

		$this->opt->toc_page_url = $this->opt->page_url ?: home_url( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );

		// collect and replace
		$content = preg_replace_callback( "/$patt_in/is", [ $this, '_make_contents_callback' ], $content, -1, $count );

		if( ! $count || $count < $this->opt->min_found ){
			unset( $this->temp ); // clear cache

			return;
		}

		$this->temp->content = $content;

	}

	/**
	 * Callback function to replace and collect contents.
	 *
	 * @param array $match
	 *
	 * @return string
	 */
	private function _make_contents_callback( $match ){
		$temp = & $this->temp;

		// it's class selector in pattern
		if( count( $match ) === 5 ){
			[ $tag, $attrs, $level_tag, $tag_txt ] = array_slice( $match, 1 );
		}
		// it's tag selector
		elseif( count( $match ) === 4 ){
			[ $tag, $attrs, $tag_txt ] = array_slice( $match, 1 );

			$level_tag = $tag; // class name
		}
		// it's class selector
		else{
			[ $tag, $attrs, $level_tag, $tag_txt ] = array_slice( $match, 4 );
		}

		if( isset( $this->temp->as_table ) ){
			$tag_desc = '';
			//if( preg_match( '/'. preg_quote($match[0], '/') .'\s*<p>((?:.(?!<\/p>))+)./', $this->temp->content, $mm ) ){
			if( preg_match( '/' . preg_quote( $match[0], '/' ) . '\s*<p>(.+?)<\/p>/', $this->temp->content, $mm ) ){
				$tag_desc = $mm[1];
			}
		}

		$opt = $this->opt; // simplify

		// if tag contains id attribute it become anchor
		if(
			$opt->anchor_attr_name
			&&
			preg_match( '/ *(' . preg_quote( $opt->anchor_attr_name, '/' ) . ')=([\'"])(.+?)\2 */i', $attrs, $id_match )
		){
			// delete 'id' or 'name' attr from attrs
			if( in_array( $id_match[1], [ 'id', 'name' ] ) ){
				$attrs = str_replace( $id_match[0], '', $attrs );
			}

			$anchor = $this->_sanitaze_anchor( $id_match[3] );
		}
		else{
			$anchor = $this->_sanitaze_anchor( $tag_txt );
		}

		$level = @ $temp->level_tags[ $level_tag ];
		if( $level > 0 ){
			$unit = preg_replace( '/\d/', '', $opt->margin ) ?: 'px';
			$sub = ( $opt->margin ? ' class="kamatoc__sub kamatoc__sub_' . $level . '" style="margin-left:' . ( $level * (int) $opt->margin ) . $unit .';"' : '' );
		}
		else{
			$sub = ' class="kamatoc__top"';
		}

		// collect contents
		// markup
		$_is_mark = $opt->markup;

		$temp->counter = empty( $temp->counter ) ? 1 : ++$temp->counter;

		// $tag_txt не может содержать A, IMG теги - удалим если надо...
		$cont_elem_txt = $tag_txt;
		if( false !== strpos( $cont_elem_txt, '</a>' ) ){
			$cont_elem_txt = preg_replace( '~<a[^>]+>|</a>~', '', $cont_elem_txt );
		}
		if( false !== strpos( $cont_elem_txt, '<img' ) ){
			$cont_elem_txt = preg_replace( '~<img[^>]+>~', '', $cont_elem_txt );
		}

		if( isset( $this->temp->as_table ) ){

			$this->contents[] = "\t".'
				<tr>
					<td'. ( $_is_mark ? ' itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"' : '' ) .'>
						<a rel="nofollow" href="'. "$opt->page_url#$anchor" .'">'. $cont_elem_txt .'</a>
						'.( $_is_mark ? ' <meta itemprop="name" content="'. esc_attr( wp_strip_all_tags( $cont_elem_txt ) ) .'" />':'' ).'
						'.( $_is_mark ? ' <meta itemprop="url" content="'. esc_attr( "$opt->toc_page_url#$anchor" ) .'" />':'' ).'
						'.( $_is_mark ? ' <meta itemprop="position" content="'. $temp->counter .'" />':'' ).'
					</td>
					<td>'. $tag_desc .'</td>
				</tr>'. "\n";
		}
		else {

			$this->contents[] = "\t".'
				<li'. $sub . ( $_is_mark ? ' itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"' : '' ) .'>
					<a rel="nofollow" href="'. $opt->page_url .'#'. $anchor .'">'. $cont_elem_txt .'</a>
					'.( $_is_mark ? ' <meta itemprop="name" content="'. esc_attr( wp_strip_all_tags( $cont_elem_txt ) ) .'" />':'' ).'
					'.( $_is_mark ? ' <meta itemprop="url" content="'. esc_attr( "$opt->toc_page_url#$anchor" ) .'" />':'' ).'
					'.( $_is_mark ? ' <meta itemprop="position" content="'. $temp->counter .'" />':'' ).'
				</li>'. "\n";
		}

		if( $opt->anchor_link ){
			$tag_txt = '<a rel="nofollow" class="kamatoc-anchlink" href="#' . $anchor . '">' . $opt->anchor_link . '</a> ' . $tag_txt;
		}

		// anchor type: 'a' or 'id'
		if( $opt->anchor_type === 'a' ){
			$new_el = '<a class="kamatoc-anchor" name="' . $anchor . '"></a>' . "\n<$tag $attrs>$tag_txt</$tag>";
		}
		else{
			$new_el = "\n<$tag id=\"$anchor\" $attrs>$tag_txt</$tag>";
		}

		$to_menu = '';
		if( $opt->to_menu ){
			// go to contents
			$to_menu = '<a rel="nofollow" class="kamatoc-gotop" href="'. $opt->page_url .'#tocmenu">'. $opt->to_menu .'</a>';

			// remove '$to_menu' if simbols beatween $to_menu too small (< 300)
			// mb_strpos( $temp->content, $match[0] ) - в 150 раз медленнее!
			$pos = strpos( $temp->content, $match[0] );
			if( empty( $temp->elpos ) ){
				$prevpos = 0;
				$temp->elpos = array( $pos );
			}
			else {
				$prevpos = end( $temp->elpos );
				$temp->elpos[] = $pos;
			}
			$simbols_count = $pos - $prevpos;

			if( $simbols_count < $opt->tomenu_simcount )
				$to_menu = '';
		}

		return $to_menu . $new_el;
	}

	/**
	 * anchor transliteration
	 *
	 * @param string $anch
	 *
	 * @return string
	 */
	private function _sanitaze_anchor( $anch ){
		$anch = strip_tags( $anch );

		$anch = apply_filters( 'kamatoc__sanitaze_anchor_before', $anch, $this );

		$anch = html_entity_decode( $anch );

		// iso9
		$anch = strtr( $anch, [
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
		] );

		$spec = preg_quote( $this->opt->spec, '/' );
		$anch = preg_replace( "/[^a-zA-Z0-9_$spec\-]+/", '-', $anch ); // все ненужное на '-'
		$anch = strtolower( trim( $anch, '-') );
		$anch = substr( $anch, 0, 70 ); // shorten

		$anch = apply_filters( 'kamatoc__sanitaze_anchor', $anch, $this );

		return self::_unique_anchor( $anch );
	}

	/**
	 * Adds number at the end if this anchor already exists.
	 *
	 * @param string $anch
	 *
	 * @return string
	 */
	public static function _unique_anchor( $anch ){
		static $anchors = [];

		// check and unique anchor
		if( isset( $anchors[ $anch ] ) ){

			$lastnum = substr( $anch, -1 );
			$lastnum = is_numeric( $lastnum ) ? $lastnum + 1 : 2;
			$anch = preg_replace( '/-\d$/', '', $anch );

			return call_user_func( __METHOD__, "$anch-$lastnum" );
		}

		$anchors[ $anch ] = 1;

		return $anch;
	}

}



