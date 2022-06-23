<?php
/** @noinspection RegExpRedundantEscape */

namespace Kama\WP;

/**
 * Contents (table of contents) for large posts.
 *
 * @author  Kama
 * @see     http://wp-kama.ru/1513
 * @version 4.3.6
 */
interface Kama_Contents_Interface {

	/**
	 * Creates an instance by specified parameters.
	 *
	 * @return object Implementation instance.
	 */
	public function __construct( array $args = [] );

	/** Processes the text, turns the shortcode in it into a table of contents. */
	public function apply_shortcode( string $content ): string;

	/** Cuts out the kamaTOC shortcode from the content. */
	public function strip_shortcode( string $content ): string;

	/** Replaces the headings in the $content, creates and returns a table of contents. */
	public function make_contents( string &$content, string $params = '' ): string;

}

class Kama_Contents implements Kama_Contents_Interface {

	use Kama_Contents__Helpers;
	use Kama_Contents__Legacy;

	private static $default_opt = [
		'margin'           => '2em',
		'selectors'        => 'h2 h3 h4',
		'to_menu'          => 'к оглавлению ↑',
		'title'            => 'Оглавление:',
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
		'leave_tags'       => true,

		// shortcode additional params
		'as_table' => false,
		'embed'    => false,
	];

	/**
	 * @var object Instance options.
	 */
	private $opt;

	/**
	 * Collects html (the contents).
	 *
	 * @var array
	 */
	protected $contents_elems;

	/**
	 * @var array
	 */
	private $temp;

	/**
	 * Create instance.
	 *
	 * @param array      $args {
	 *     Parameters.
	 *
	 *     @type string      $margin           Отступ слева у подразделов в px|em|rem.
	 *     @type string      $selectors        HTML теги по котором будет строиться оглавление: 'h2 h3 h4'.
	 *                                         Порядок определяет уровень вложености.
	 *                                         Можно указать строку или массив: [ 'h2', 'h3', 'h4' ] или 'h2 h3 h4'.
	 *                                         Можно указать атрибут class: 'h2 .class_name'.
	 *                                         Если нужно чтобы разные теги были на одном уровне,
	 *                                         указываем их через |: 'h2|dt h3' или [ 'h2|dt', 'h3' ].
	 *     @type string      $to_menu          Ссылка на возврат к оглавлению. '' - убрать ссылку.
	 *     @type string      $title            Заголовок. '' - убрать заголовок.
	 *     @type string      $js               JS код (добавляется после HTML кода)
	 *     @type int         $min_found        Минимальное количество найденных тегов, чтобы оглавление выводилось.
	 *     @type int         $min_length       Минимальная длина (символов) текста, чтобы оглавление выводилось.
	 *     @type string      $page_url         Ссылка на страницу для которой собирается оглавление.
	 *                                         Если оглавление выводиться на другой странице...
	 *     @type string      $shortcode        Название шоткода. По умолчанию: 'contents'.
	 *     @type string      $spec             Оставлять символы в анкорах. For example: `'.+$*=`.
	 *     @type string      $anchor_type      Какой тип анкора использовать: 'a' - `<a name="anchor"></a>` или 'id'.
	 *     @type string      $anchor_attr_name Название атрибута тега из значения которого будет браться
	 *                                         анкор (если этот атрибут есть у тега). Ставим '', чтобы отключить такую проверку...
	 *     @type bool        $markup           Включить микроразметку?
	 *     @type string      $anchor_link      Добавить 'знак' перед подзаголовком статьи со ссылкой
	 *                                         на текущий анкор заголовка. Укажите '#', '&' или что вам нравится.
	 *     @type int         $tomenu_simcount  Минимальное количество символов между заголовками содержания,
	 *                                         для которых нужно выводить ссылку "к содержанию".
	 *                                         Не имеет смысла, если параметр 'to_menu' отключен. С целью производительности,
	 *                                         кириллица считается без учета кодировки. Поэтому 800 символов кириллицы -
	 *                                         это примерно 1600 символов в этом параметре. 800 - расчет для сайтов на кириллице.
	 *     @type bool|string $leave_tags       Нужно ли оставлять HTML теги в элементах оглавления. С версии 4.3.4.
	 *                                         Можно указать только какие теги нужно оставлять. Пр: `'<b><strong><var><code>'`.
	 *
	 * }
	 */
	public function __construct( array $args = [] ) {
		$this->set_opt( $args );
	}

	protected function set_opt( $args = [] ): void {
		$this->opt = (object) array_merge( self::$default_opt, (array) $args );
	}

	/**
	 * Processes the text, turns the shortcode in it into a table of contents.
	 * Use shortcode [contents] or [[contents]] to show shortcode as it is.
	 *
	 * @param string $content  The text with shortcode.
	 *
	 * @return string Processed text with a table of contents, if it has a shotcode.
	 */
	public function apply_shortcode( string $content ): string {

		$shortcode = $this->opt->shortcode;

		if( false === strpos( $content, "[$shortcode" ) ){
			return $content;
		}

		// get contents data
		// use `[[contents` to escape the shortcode
		/** @noinspection RegExpRedundantEscape */
		if( ! preg_match( "/^(.*)(?<!\[)\[$shortcode([^\]]*)\](.*)$/su", $content, $m ) ){
			return $content;
		}

		$contents = $this->make_contents( $m[3], $m[2] );

		return $m[1] . $contents . $m[3];
	}

	/**
	 * Cuts out the kamaTOC shortcode from the content.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function strip_shortcode( string $content ): string {
		return preg_replace( '~\[' . $this->opt->shortcode . '[^\]]*\]~', '', $content );
	}

	/**
	 * Replaces the headings in the past text (by ref), creates and returns a table of contents.
	 *
	 * @param string $content  The text from which you want to create a table of contents.
	 * @param string $params   Array of HTML tags to look for in the past text.
	 *                         "h2 .foo" - Specify: tag names "h2 h3" or names of CSS classes ".foo .foo2".
	 *                         "embed" - Add "embed" mark here to get `<ul>` tag only (without header and wrapper block).
	 *                         "as_table="Title|Desc" - Show as table. First sentence after header will be taken for description.
	 *                         It can be useful for use contents inside the text as a list.
	 *
	 * @return string table of contents HTML code.
	 */
	public function make_contents( string &$content, string $params = '' ): string {

		// text is too short
		if( mb_strlen( strip_tags( $content ) ) < $this->opt->min_length ){
			return '';
		}

		$this->temp = new \stdClass();
		$this->contents_elems = [];

		$params_array = $this->parse_string_params( $params );
		$tags = $this->split_params_and_tags( $params_array );
		$tags = $this->get_actual_tags( $tags, $content );

		if( ! $tags ){
			return '';
		}

		$this->collect_toc( $content, $tags );

		$contents = $this->toc_html();

		unset( $this->temp ); // clear cache

		return $contents;
	}

	/**
	 * Parse TAGS.
	 *
	 * @param string $params
	 * @param        $mm
	 * @param string $content
	 *
	 * @return array
	 */
	protected function parse_string_params( string $params ): array {

		if( ! $params ){
			$params = $this->opt->selectors;
		}

		$this->temp->original_string_params = $params;

		// $extra_tags

		$extra_tags = [];

		if( preg_match( '/(as_table)="([^"]+)"/', $params, $mm ) ){

			$extra_tags[ $mm[1] ] = explode( '|', $mm[2] );
			$params = str_replace( " $mm[0]", '', $params ); // cut
		}

		$params = array_map( 'trim', preg_split( '/[ ,|]+/', $params ) );

		$params += $extra_tags;

		return array_filter( $params );
	}

	/**
	 * Split parameters and tags.
	 *
	 * @param array  $params
	 * @param string $content
	 *
	 * @return array
	 */
	protected function split_params_and_tags( array $params ): array {

		$tags = [];

		foreach( $params as $key => $val ){

			// extra tags
			if( 'as_table' === $key ){
				$this->opt->as_table = $val;
			}
			elseif( 'embed' === $val || 'embed' === $key ){
				$this->opt->embed = true;
			}
			elseif( 'no_to_menu' === $val || 'no_to_menu' === $key ){
				$this->opt->to_menu = false;
			}
			else {
				$tags[ $key ] = $val;
			}
		}

		return $tags;
	}

	// remove tag if it's not exists in content (for performance)
	protected function get_actual_tags( array $tags, string $content ): array {

		foreach( $tags as $key => $tag ){

			$patt = ( $tag[0] === '.' )
				? 'class=[\'"][^\'"]*' . substr( $tag, 1 )
				: "<$tag";

			if( ! preg_match( "/$patt/i", $content ) ){
				unset( $tags[ $key ] );
			}
		}

		return $tags;
	}

	/**
	 * Collect TOC (all titles) from cpecified content.
	 * Replace HTML in specified content.
	 *
	 * @param string $content Changes by ref.
	 * @param array  $tags    HTML tags (selectors) to collect from content.
	 *
	 * @return void
	 */
	protected function collect_toc( string &$content, array $tags ): void {

		$this->_set_level_tags_and_regex_patt( $tags );

		$patt_in = [];

		if( $this->temp->tag_regex_patt ){
			$patt_in[] = '(?:<(' . implode( '|', $this->temp->tag_regex_patt ) . ')([^>]*)>(.*?)<\/\1>)';
		}

		if( $this->temp->class_regex_patt ){
			$patt_in[] = '(?:<([^ >]+) ([^>]*class=["\'][^>]*(' . implode( '|', $this->temp->class_regex_patt ) . ')[^>]*["\'][^>]*)>(.*?)<\/' . ( $patt_in ? '\4' : '\1' ) . '>)';
		}

		$patt_in = implode( '|', $patt_in );

		$this->temp->content = $content;

		$this->opt->toc_page_url = $this->opt->page_url ?: home_url( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );

		// collect and replace
		$content = preg_replace_callback( "/$patt_in/is", [ $this, 'collect_toc_replace_callback' ], $content, -1, $count );

		if( ! $count || $count < $this->opt->min_found ){
			unset( $this->temp ); // clear cache

			return;
		}

		$this->temp->content = $content;
	}

	protected function _set_level_tags_and_regex_patt( array $tags ): void {

		// group HTML classes & tags for regex patterns
		$tag_regex_patt = $class_regex_patt = $level_tags = [];

		foreach( $tags as $tag ){
			// class
			if( $tag[0] === '.' ){
				$tag = substr( $tag, 1 );
				$_ln = &$class_regex_patt;
			}
			// html tag
			else{
				$_ln = &$tag_regex_patt;
			}

			$_ln[] = $tag;
			$level_tags[] = $tag;
		}

		$level_tags = array_flip( $level_tags );

		// fix levels if it's not start from zero
		if( reset( $level_tags ) !== 0 ){
			while( reset( $level_tags ) !== 0 ){
				$level_tags = array_map( static function( $val ) {
					return $val - 1;
				}, $level_tags );
			}
		}

		// set equal level if tags specified with tag1|tag2
		$_prev_tag = '';
		foreach( $level_tags as $tag => $lvl ){

			if( $_prev_tag && false !== strpos( $this->temp->original_string_params, "$_prev_tag|$tag" ) ){
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
		$this->temp->tag_regex_patt = $tag_regex_patt;
		$this->temp->class_regex_patt = $class_regex_patt;
	}

	/**
	 * Callback function to replace and collect contents.
	 *
	 * @param array $match
	 *
	 * @return string
	 */
	protected function collect_toc_replace_callback( $match ): string {

		[ $full_match, $tag, $attrs, $level_tag, $tag_txt ] = $this->_replace_parse_match( $match );

		$this->temp->counter = empty( $this->temp->counter ) ? 1 : ++$this->temp->counter;

		$toc_elem_text = $this->_strip_tags_in_elem_txt( $tag_txt );

		$anchor = $this->_toc_element_anchor( $tag_txt, $attrs );

		$elem_html = $this->toc_element_html( $full_match, $anchor, $toc_elem_text, $level_tag );

		$this->contents_elems[] = "\t$elem_html\n";

		if( $this->opt->anchor_link ){
			$tag_txt = '<a rel="nofollow" class="kamatoc-anchlink" href="#' . $anchor . '">' . $this->opt->anchor_link . '</a> ' . $tag_txt;
		}

		// anchor type: 'a' or 'id'
		if( $this->opt->anchor_type === 'a' ){
			$new_el = '<a class="kamatoc-anchor" name="' . $anchor . '"></a>' . "\n<$tag $attrs>$tag_txt</$tag>";
		}
		else{
			$new_el = "\n<$tag id=\"$anchor\" $attrs>$tag_txt</$tag>";
		}

		$to_menu = $this->_to_menu( $full_match );

		return $to_menu . $new_el;
	}

	protected function toc_element_html( $full_match, $anchor, $toc_elem_text, $level_tag ): string {

		// table
		if( $this->opt->as_table ){

			// take first sentence
			$quoted_match = preg_quote( $full_match, '/' );
			//preg_match( "/$quoted_match\s*<p>((?:.(?!<\/p>))+)/", $this->temp->content, $mm )
			preg_match( "/$quoted_match\s*<p>(.+?)<\/p>/", $this->temp->content, $mm );
			$tag_desc = $mm ? $mm[1] : '';

			$elem_html = '
				<tr>
					<td {ListElement}>
						<a rel="nofollow" href="' . "{$this->opt->page_url}#$anchor" . '">' . $toc_elem_text . '</a>
						{ListElement_item}
						{ListElement_name}
						{ListElement_pos}
					</td>
					<td>' . $tag_desc . '</td>
				</tr>';
		}
		// list (li)
		else{

			$level = (int) @ $this->temp->level_tags[ $level_tag ];
			if( $level > 0 ){
				$unit = preg_replace( '/\d/', '', $this->opt->margin ) ?: 'px';
				$elem_classes = "kamatoc__sub kamatoc__sub_{$level}";
				$elem_attr = $this->opt->margin ? ' style="margin-left:' . ( $level * (int) $this->opt->margin ) . $unit . ';"' : '';
			}
			else{
				$elem_classes = 'kamatoc__top';
				$elem_attr = '';
			}

			$elem_html = '
				<li class="'. $elem_classes .'" ' . $elem_attr . '{ListElement}>
					<a rel="nofollow" href="' . "{$this->opt->page_url}#$anchor" . '">' . $toc_elem_text . '</a>
					{ListElement_item}
					{ListElement_name}
					{ListElement_pos}
				</li>';
		}

		$ismk = $this->opt->markup;

		$elem_html = strtr( $elem_html, [
			'{ListElement}'      => $ismk ? ' itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"' : '',
			'{ListElement_item}' => $ismk ? ' <meta itemprop="item" content="' . esc_attr( "{$this->opt->toc_page_url}#$anchor" ) . '" />' : '',
			'{ListElement_name}' => $ismk ? ' <meta itemprop="name" content="' . esc_attr( wp_strip_all_tags( $toc_elem_text ) ) . '" />' : '',
			'{ListElement_pos}'  => $ismk ? ' <meta itemprop="position" content="' . $this->temp->counter . '" />' : '',
		] );

		return $elem_html;
	}

	/**
	 * @param array $match
	 *
	 * @return array
	 */
	protected function _replace_parse_match( $match ){

		$full_match = $match[0];

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

		return [ $full_match, $tag, $attrs, $level_tag, $tag_txt ];
	}

	protected function _to_menu( $full_match ){

		if( ! $this->opt->to_menu ){
			return '';
		}

		// go to contents
		$to_menu = '<a rel="nofollow" class="kamatoc-gotop" href="' . "{$this->opt->page_url}#tocmenu" . '">' . $this->opt->to_menu . '</a>';

		// remove '$to_menu' if simbols beatween $to_menu too small (< 300)

		// mb_strpos( $this->temp->content, $full_match ) - в 150 раз медленнее!
		$elpos = strpos( $this->temp->content, $full_match );

		if( empty( $this->temp->elpos ) ){
			$prevpos = 0;
			$this->temp->elpos = [ $elpos ];
		}
		else{
			$prevpos = end( $this->temp->elpos );
			$this->temp->elpos[] = $elpos;
		}
		$simbols_count = $elpos - $prevpos;

		if( $simbols_count < $this->opt->tomenu_simcount ){
			$to_menu = '';
		}

		return $to_menu;
	}

	/**
	 * @return string
	 */
	protected function toc_html(): string {

		// table
		if( $this->opt->as_table ){

			$contents = '
			<table id="tocmenu" class="kamatoc kamatoc_js" {ItemList}>
				{ItemName}
				<thead>
					<tr>
						<th>' . esc_html( $this->opt->as_table[0] ) . '</th>
						<th>' . esc_html( $this->opt->as_table[1] ) . '</th>
					</tr>
				</thead>
				<tbody>
					' . implode( '', $this->contents_elems ) . '
				</tbody>
			</table>';
		}
		// list
		else{

			$add_wrapper = $this->opt->title && ! $this->opt->embed;
			$contents_wrap_patt = '%s';

			if( $add_wrapper ){

				$contents_wrap_patt = '
					<div class="kamatoc-wrap">
						<div class="kamatoc-wrap__title kamatoc_wrap_title_js">' . $this->opt->title . '</div>
						'. $contents_wrap_patt .'
					</div>
				';
			}

			$contents = '
				<ul id="tocmenu" class="kamatoc kamatoc_js" {ItemList}>
					{ItemName}
					' . implode( '', $this->contents_elems ) . '
				</ul>';

			$contents = sprintf( $contents_wrap_patt, $contents );
		}

		$js_code = $this->opt->js
			? '<script>' . preg_replace( '/[\n\t ]+/', ' ', $this->opt->js ) . '</script>'
			: '';

		$contents = $this->add_markup( $contents );

		/**
		 * Allow to change result contents string.
		 *
		 * @param string        $contents
		 * @param Kama_Contents $inst
		 */
		return apply_filters( 'kamatoc__contents', "$contents\n$js_code", $this );
	}

	protected function add_markup( $html ){

		$is = $this->opt->markup;

		$replace = [
			'{ItemList}' => $is ? ' itemscope itemtype="https://schema.org/ItemList"' : '',
			'{ItemName}' => $is ? '<meta itemprop="name" content="' . esc_attr( wp_strip_all_tags( $this->opt->title ) ) . '" />' : '',
		];

		return strtr( $html, $replace );
	}

}

trait Kama_Contents__Helpers {

	/**
	 * @param string $tag_txt
	 * @param string $attrs
	 *
	 * @return string
	 */
	protected function _toc_element_anchor( $tag_txt, $attrs ){

		// if tag contains id|name|... attribute it becomes anchor.
		if(
			$this->opt->anchor_attr_name
			&&
			preg_match( '/ *(' . preg_quote( $this->opt->anchor_attr_name, '/' ) . ')=([\'"])(.+?)\2 */i', $attrs, $match_anchor_attr )
		){
			// delete 'id' or 'name' attr from attrs
			if( in_array( $match_anchor_attr[1], [ 'id', 'name' ], true ) ){
				$attrs = str_replace( $match_anchor_attr[0], '', $attrs );
			}

			$anchor = $this->_sanitaze_anchor( $match_anchor_attr[3] );
		}
		else{
			$anchor = $this->_sanitaze_anchor( $tag_txt );
		}

		return $anchor;
	}

	/**
	 * @param string $tag_txt
	 *
	 * @return string
	 */
	protected function _strip_tags_in_elem_txt( $tag_txt ){

		// strip all tags
		if( ! $this->opt->leave_tags ){
			$tag_txt = strip_tags( $tag_txt );
		}
		// strip all tags, except specified
		elseif( is_string( $this->opt->leave_tags ) ){
			$tag_txt = strip_tags( $tag_txt, $this->opt->leave_tags );
		}
		// leave tags
		// $tag_txt не может содержать A, IMG теги - удалим если надо...
		else{

			if( false !== strpos( $tag_txt, '</a>' ) ){
				$tag_txt = preg_replace( '~<a[^>]+>|</a>~', '', $tag_txt );
			}
			if( false !== strpos( $tag_txt, '<img' ) ){
				$tag_txt = preg_replace( '~<img[^>]+>~', '', $tag_txt );
			}
		}

		return $tag_txt;
	}

	/**
	 * anchor transliteration
	 *
	 * @param string $anch
	 *
	 * @return string
	 */
	protected function _sanitaze_anchor( $anch ) {

		$anch = strip_tags( $anch );

		$anch = apply_filters( 'kamatoc__sanitaze_anchor_before', $anch, $this );

		$anch = html_entity_decode( $anch );

		// iso9
		$anch = strtr( $anch, [
			'А' => 'A',
			'Б' => 'B',
			'В' => 'V',
			'Г' => 'G',
			'Д' => 'D',
			'Е' => 'E',
			'Ё' => 'YO',
			'Ж' => 'ZH',
			'З' => 'Z',
			'И' => 'I',
			'Й' => 'J',
			'К' => 'K',
			'Л' => 'L',
			'М' => 'M',
			'Н' => 'N',
			'О' => 'O',
			'П' => 'P',
			'Р' => 'R',
			'С' => 'S',
			'Т' => 'T',
			'У' => 'U',
			'Ф' => 'F',
			'Х' => 'H',
			'Ц' => 'TS',
			'Ч' => 'CH',
			'Ш' => 'SH',
			'Щ' => 'SHH',
			'Ъ' => '',
			'Ы' => 'Y',
			'Ь' => '',
			'Э' => 'E',
			'Ю' => 'YU',
			'Я' => 'YA',
			// small
			'а' => 'a',
			'б' => 'b',
			'в' => 'v',
			'г' => 'g',
			'д' => 'd',
			'е' => 'e',
			'ё' => 'yo',
			'ж' => 'zh',
			'з' => 'z',
			'и' => 'i',
			'й' => 'j',
			'к' => 'k',
			'л' => 'l',
			'м' => 'm',
			'н' => 'n',
			'о' => 'o',
			'п' => 'p',
			'р' => 'r',
			'с' => 's',
			'т' => 't',
			'у' => 'u',
			'ф' => 'f',
			'х' => 'h',
			'ц' => 'ts',
			'ч' => 'ch',
			'ш' => 'sh',
			'щ' => 'shh',
			'ъ' => '',
			'ы' => 'y',
			'ь' => '',
			'э' => 'e',
			'ю' => 'yu',
			'я' => 'ya',
			// other
			'Ѓ' => 'G',
			'Ґ' => 'G',
			'Є' => 'YE',
			'Ѕ' => 'Z',
			'Ј' => 'J',
			'І' => 'I',
			'Ї' => 'YI',
			'Ќ' => 'K',
			'Љ' => 'L',
			'Њ' => 'N',
			'Ў' => 'U',
			'Џ' => 'DH',
			'ѓ' => 'g',
			'ґ' => 'g',
			'є' => 'ye',
			'ѕ' => 'z',
			'ј' => 'j',
			'і' => 'i',
			'ї' => 'yi',
			'ќ' => 'k',
			'љ' => 'l',
			'њ' => 'n',
			'ў' => 'u',
			'џ' => 'dh',
		] );

		$spec = preg_quote( $this->opt->spec, '/' );
		$anch = preg_replace( "/[^a-zA-Z0-9_$spec\-]+/", '-', $anch ); // все ненужное на '-'
		$anch = strtolower( trim( $anch, '-' ) );
		$anch = substr( $anch, 0, 70 ); // shorten

		$anch = apply_filters( 'kamatoc__sanitaze_anchor', $anch, $this );

		return $this->_unique_anchor( $anch );
	}

	/**
	 * Adds number at the end if this anchor already exists.
	 *
	 * @param string $anch
	 *
	 * @return string
	 */
	protected function _unique_anchor( string $anch ): string {

		if( ! isset( $this->temp->anchors ) ){
			$this->temp->anchors = [];
		}

		// check and unique anchor
		if( isset( $this->temp->anchors[ $anch ] ) ){

			$lastnum = substr( $anch, -1 );
			$lastnum = is_numeric( $lastnum ) ? $lastnum + 1 : 2;
			$anch = preg_replace( '/-\d$/', '', $anch );

			return call_user_func( __METHOD__, "$anch-$lastnum" );
		}

		$this->temp->anchors[ $anch ] = 1;

		return $anch;
	}

}

trait Kama_Contents__Legacy {

	/**
	 * Creates an instance of Kama_Contents for later use.
	 *
	 * @param array $args
	 *
	 * @return Kama_Contents
	 */
	public static function init( array $args = [] ) {
		static $inst;

		$args = array_intersect_key( $args, self::$default_opt ); // leave allowed only
		$inst_key = md5( serialize( $args ) );

		if( empty( $inst[ $inst_key ] ) ){
			$inst[ $inst_key ] = new self();
			$inst[ $inst_key ]->set_opt( $args );
		}

		return $inst[ $inst_key ];
	}

	/**
	 * Alias of {@see apply_shortcode()}.
	 */
	public function shortcode( string $content ): string {
		return $this->apply_shortcode( $content );
	}

}

