<?php
/**
 * Contents (table of contents) for large posts.
 *
 * @author  Kama
 * @see     http://wp-kama.com/2216
 * @require PHP 7.1
 *
 * @version 4.3.13
 */
/** @noinspection PhpMultipleClassesDeclarationsInOneFile */
/** @noinspection RegExpRedundantEscape */

namespace Kama\WP;

interface Kama_Contents_Interface {

	/** Creates an instance by specified parameters. */
	public function __construct( array $args = [] );

	/** Processes the text, turns the shortcode in it into a table of contents. */
	public function apply_shortcode( string $content ): string;

	/** Cuts out the kamaTOC shortcode from the content. */
	public function strip_shortcode( string $content ): string;

	/** Replaces the headings in the $content, creates and returns a table of contents. */
	public function make_contents( string &$content, string $params = '' ): string;

}

class Kama_Contents implements Kama_Contents_Interface {

	use Kama_Contents__Html;
	use Kama_Contents__Helpers;
	use Kama_Contents__Legacy;

	private static $default_opt = [
		'margin'           => '2em',
		'selectors'        => 'h2 h3 h4',
		'to_menu'          => 'contents ↑',
		'title'            => 'Table of Contents:',
		'js'               => '',
		'min_found'        => 1,
		'min_length'       => 500,
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
	 * @var TOC_Elem[]
	 */
	protected $toc_elems;

	/**
	 * @var array
	 */
	private $temp;

	/**
	 * @param array      $args {
	 *     Parameters.
	 *
	 *     @type string      $margin           Отступ слева у подразделов в px|em|rem.
	 *     @type string      $selectors        HTML теги по котором будет строиться оглавление: 'h2 h3 h4'.
	 *                                         Порядок определяет уровень вложености.
	 *                                         Можно указать строку/массив: 'h2 h3 h4' или [ 'h2', 'h3', 'h4' ].
	 *                                         Можно указать атрибут/class: 'h2 .class_name'.
	 *                                         Если нужно, чтобы разные теги были на одном уровне,
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
	 * @param string $content The text with shortcode.
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
		if( ! preg_match( "/^(.*)(?<!\[)\[$shortcode([^\]]*)\](.*)$/su", $content, $m ) ){
			return $content;
		}

		$toc = $this->make_contents( $m[3], $m[2] );

		return $m[1] . $toc . $m[3];
	}

	/**
	 * Cuts out the kamaTOC shortcode from the content.
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
	 * @return string Table of contents HTML.
	 */
	public function make_contents( string &$content, string $params = '' ): string {

		// text is too short
		if( mb_strlen( strip_tags( $content ) ) < $this->opt->min_length ){
			return '';
		}

		$this->temp = new \stdClass();

		$params_array = $this->parse_string_params( $params );
		$tags = $this->split_params_and_tags( $params_array );
		$tags = $this->get_actual_tags( $tags, $content );

		if( ! $tags ){
			unset( $this->temp );

			return '';
		}

		$this->temp->toc_page_url = $this->opt->page_url ?: home_url( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );

		$this->collect_toc( $content, $tags );

		if( count( $this->toc_elems ) < $this->opt->min_found ){
			unset( $this->temp );

			return '';
		}

		$contents = $this->toc_html();

		unset( $this->temp );

		return $contents;
	}

	protected function parse_string_params( string $params ): array {

		$this->temp->original_string_params = $params;

		$extra_tags = [];

		if( preg_match( '/(as_table)="([^"]+)"/', $params, $mm ) ){

			$extra_tags[ $mm[1] ] = explode( '|', $mm[2] );
			$params = str_replace( " $mm[0]", '', $params ); // cut
		}

		$params = array_map( 'trim', preg_split( '/[ ,|]+/', $params ) );

		$params += $extra_tags;

		return array_filter( $params );
	}

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

		if( ! $tags ){
			$tags = is_array( $this->opt->selectors )
				? $this->opt->selectors
				: explode( ' ', $this->opt->selectors );
		}

		return $tags;
	}

	/**
	 * Remove tag if it's not exists in content (for performance).
	 */
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
	 */
	protected function collect_toc( string & $content, array $tags ): void {

		$this->toc_elems = [];

		$this->_set_tags_levels_and_regex_patt( $tags );

		$patt_in = [];

		if( $this->temp->tag_regex_patt ){
			$tags_in = implode( '|', $this->temp->tag_regex_patt );
			$patt_in[] = "(?:<($tags_in)([^>]*)>(.*?)<\/\\1>)";
		}

		if( $this->temp->class_regex_patt ){
			$class_in = implode( '|', $this->temp->class_regex_patt );
			$patt_in[] = "(?:<([^ >]+) ([^>]*class=[\"'][^>]*($class_in)[^>]*[\"'][^>]*)>(.*?)<\/" . ( $patt_in ? '\4' : '\1' ) . '>)';
		}

		$patt_in = implode( '|', $patt_in );

		// collect and replace
		$this->temp->orig_content = $content;

		$new_content = (string) preg_replace_callback( "/$patt_in/is", [ $this, 'collect_toc_replace_callback' ], $content, -1 );

		if( count( $this->toc_elems ) >= $this->opt->min_found ){
			$content = $new_content;
		}

	}

	protected function _replace_parse_match( array $match ): array {

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

	protected function _set_tags_levels_and_regex_patt( array $tags ): void {

		// group HTML classes & tags for regex patterns
		$tag_regex_patt = $class_regex_patt = $tags_levels = [];

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
			$tags_levels[] = $tag;
		}

		$tags_levels = array_flip( $tags_levels );

		// fix levels if it's not start from zero
		if( reset( $tags_levels ) !== 0 ){
			while( reset( $tags_levels ) !== 0 ){
				$tags_levels = array_map( static function( $val ) {
					return $val - 1;
				}, $tags_levels );
			}
		}

		// set equal level if tags specified with tag1|tag2
		$_prev_tag = '';
		foreach( $tags_levels as $tag => $lvl ){

			if( $_prev_tag && false !== strpos( $this->temp->original_string_params, "$_prev_tag|$tag" ) ){
				$tags_levels[ $tag ] = $_prev_lvl ?? 0;
			}

			$_prev_tag = $tag;
			$_prev_lvl = $lvl;
		}

		// set levels one by one, if they have been broken after the last operation
		$_prev_lvl = 0;
		foreach( $tags_levels as & $lvl ){

			// fix next lvl - it's wrong
			if( ! in_array( $lvl, [ $_prev_lvl, $_prev_lvl + 1 ], true ) ){

				$lvl = $_prev_lvl + 1;
			}

			$_prev_lvl = $lvl;
		}
		unset( $lvl );

		$this->temp->tags_levels = $tags_levels;
		$this->temp->tag_regex_patt = $tag_regex_patt;
		$this->temp->class_regex_patt = $class_regex_patt;
	}

	/**
	 * Callback function to replace and collect contents.
	 */
	protected function collect_toc_replace_callback( array $match ): string {

		[ $full_match, $tag, $attrs, $level_tag, $tag_text ] = $this->_replace_parse_match( $match );

		$this->temp->counter = ( $this->temp->counter ?? 0 ) + 1;

		$elem = new TOC_Elem( [
			'full_match' => $full_match,
			'tag'        => $tag,
			'anchor'     => $this->_toc_element_anchor( $tag_text, $attrs ),
			'text'       => $this->_strip_tags_in_elem_txt( $tag_text ),
			'level'      => $this->temp->tags_levels[ $level_tag ] ?? 0,
			'position'   => $this->temp->counter,
		] );

		$this->toc_elems[] = $elem;

		if( $this->opt->anchor_link ){
			$tag_text = '<a rel="nofollow" class="kamatoc-anchlink" href="#' . $elem->anchor . '">' . $this->opt->anchor_link . '</a> ' . $tag_text;
		}

		// anchor type: 'a' or 'id'
		if( $this->opt->anchor_type === 'a' ){
			$new_el = '<a class="kamatoc-anchor" name="' . $elem->anchor . '"></a>' . "\n<$tag $attrs>$tag_text</$tag>";
		}
		else{
			$new_el = "\n<$tag id=\"$elem->anchor\" $attrs>$tag_text</$tag>";
		}

		return $this->_to_menu_link_html( $elem ) . $new_el;
	}

	protected function _to_menu_link_html( TOC_Elem $elem ): string {

		if( ! $this->opt->to_menu ){
			return '';
		}

		// mb_strpos( $this->temp->orig_content, $full_match ) - в 150 раз медленнее!
		$el_strpos = strpos( $this->temp->orig_content, $elem->full_match );

		if( empty( $this->temp->el_strpos ) ){
			$prev_el_strpos = 0;
			$this->temp->el_strpos = [ $el_strpos ];
		}
		else{
			$prev_el_strpos = end( $this->temp->el_strpos );
			$this->temp->el_strpos[] = $el_strpos;
		}
		$simbols_count = $el_strpos - $prev_el_strpos;

		// Don't show to_menu link if simbols count beatween two elements is too small (< 300)
		if( $simbols_count < $this->opt->tomenu_simcount ){
			return '';
		}

		return sprintf( '<a rel="nofollow" class="kamatoc-gotop" href="%s">%s</a>',
			"{$this->opt->page_url}#tocmenu", $this->opt->to_menu
		);
	}

}

trait Kama_Contents__Html {

	protected function _toc_html(): string {

		$toc = '';
		foreach( $this->toc_elems as $elem ){
			$elem_html = $this->render_item_html( $elem );
			$toc .= "\t$elem_html\n";
		}

		return $toc;
	}

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
					' . $this->_toc_html() . '
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
					' . $this->_toc_html() . '
				</ul>';

			$contents = sprintf( $contents_wrap_patt, $contents );
		}

		$js_code = $this->opt->js
			? '<script>' . preg_replace( '/[\n\t ]+/', ' ', $this->opt->js ) . '</script>'
			: '';

		$contents = $this->replace_markup( $contents );

		/**
		 * Allow to change result contents string.
		 *
		 * @param string        $contents
		 * @param Kama_Contents $inst
		 */
		return apply_filters( 'kamatoc__contents', "$contents\n$js_code", $this );
	}

	protected function render_item_html( TOC_Elem $elem ): string {

		// table
		if( $this->opt->as_table ){

			// take first sentence
			$quoted_match = preg_quote( $elem->full_match, '/' );
			//preg_match( "/$quoted_match\s*<p>((?:.(?!<\/p>))+)/", $this->temp->orig_content, $mm )
			preg_match( "/$quoted_match\s*<p>(.+?)<\/p>/", $this->temp->orig_content, $mm );
			$tag_desc = $mm ? $mm[1] : '';

			$elem_html = '
				<tr>
					<td {ListElement}>
						<a rel="nofollow" href="' . "{$this->opt->page_url}#$elem->anchor" . '">' . $elem->text . '</a>
						{ListElement_item}
						{ListElement_name}
						{ListElement_pos}
					</td>
					<td>' . $tag_desc . '</td>
				</tr>';
		}
		// list (li)
		else{

			if( $elem->level > 0 ){
				$unit = preg_replace( '/\d/', '', $this->opt->margin ) ?: 'px';
				$elem_classes = "kamatoc__sub kamatoc__sub_{$elem->level}";
				$elem_attr = $this->opt->margin ? ' style="margin-left:' . ( $elem->level * (int) $this->opt->margin ) . $unit . ';"' : '';
			}
			else{
				$elem_classes = 'kamatoc__top';
				$elem_attr = '';
			}

			$elem_html = '
				<li class="'. $elem_classes .'" ' . $elem_attr . '{ListElement}>
					<a rel="nofollow" href="' . "{$this->opt->page_url}#$elem->anchor" . '">' . $elem->text . '</a>
					{ListElement_item}
					{ListElement_name}
					{ListElement_pos}
				</li>';
		}

		$elem_html = $this->replace_elem_markup( $elem_html, $elem );

		/**
		 * Allow to change single TOC element HTML.
		 *
		 * @param string $elem_html
		 */
		return apply_filters( 'kamatoc__elem_html', $elem_html );
	}

	protected function replace_markup( $html ): string {

		$is = $this->opt->markup;

		$replace = [
			'{ItemList}' => $is ? ' itemscope itemtype="https://schema.org/ItemList"' : '',
			'{ItemName}' => $is ? '<meta itemprop="name" content="' . esc_attr( wp_strip_all_tags( $this->opt->title ) ) . '" />' : '',
		];

		return strtr( $html, $replace );
	}

	protected function replace_elem_markup( $html, TOC_Elem $elem ): string {

		$is = $this->opt->markup;

		$replace = [
			'{ListElement}'      => $is ? ' itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"' : '',
			'{ListElement_item}' => $is ? ' <meta itemprop="item" content="' . esc_attr( "{$this->temp->toc_page_url}#$elem->anchor" ) . '" />' : '',
			'{ListElement_name}' => $is ? ' <meta itemprop="name" content="' . esc_attr( wp_strip_all_tags( $elem->text ) ) . '" />' : '',
			'{ListElement_pos}'  => $is ? ' <meta itemprop="position" content="' . (int) $elem->position . '" />' : '',
		];

		return strtr( $html, $replace );
	}

}

trait Kama_Contents__Helpers {

	protected function _toc_element_anchor( string $tag_txt, string $attrs ): string {

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

	protected function _strip_tags_in_elem_txt( string $tag_txt ): string {

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
	 * Anchor transliteration.
	 */
	protected function _sanitaze_anchor( string $anch ): string {

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

			return $this->{ __FUNCTION__ }( "$anch-$lastnum" );
		}

		$this->temp->anchors[ $anch ] = 1;

		return $anch;
	}

}

trait Kama_Contents__Legacy {

	/**
	 * Creates an instance of Kama_Contents for later use.
	 */
	public static function init( array $args = [] ): Kama_Contents {
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

class TOC_Elem {

	/** @var string */
	public $full_match;

	/** @var string */
	public $tag;

	/** @var string */
	public $anchor;

	/** @var string */
	public $text;

	/** @var int */
	public $level;

	/** @var int */
	public $position;

	public function __construct( array $data ){

		foreach( $data as $key => $val ){
			$this->$key = $val;
		}
	}

}


