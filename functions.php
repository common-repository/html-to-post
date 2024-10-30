<?php
# 허접하지만 자존심 상 제 프레임워크를 상속 받아요.
class sjHtmlToPost extends Framework_Sujin_Plugin {
	private static $instance = false;

	protected $dir;
	protected $dir_plugin;
	protected $dir_html;
	protected $dir_css;
	protected $dir_script;

	protected function __construct() {
		$this->debug = true;
		$this->get_dir();
		$this->trigger_hooks();

		$this->text_domain = "html2post";
		parent::__construct();
	}

	private function trigger_hooks() {
		register_activation_hook(__FILE__, array(&$this, 'activate_plgin'));

		if (!is_admin()) {
			add_filter("the_content", array(&$this, "replace_with_html"));
		}

		# 지정한 CSS와 스크립트를 삽입해요.
		add_action("wp_enqueue_scripts", array(&$this, "wp_enqueue_scripts"));

		# 어드민에 스크립트를 삽입해요.
		add_action("admin_enqueue_scripts", array(&$this, "admin_enqueue_scripts"));

		add_action("add_meta_boxes", array(&$this, "set_meta_box"), 15);
		add_action("save_post", array(&$this, "save_post"));

		add_shortcode('html2post', array(&$this, "short_code"));

		if ($this->debug) {
			$this->activate_plgin();
		}
	}

	function short_code($atts) {
		global $post;

		$content = $this->make_html_from_file($post);
		return $content;
	}

	# HTML을 위한 디렉토리를 만들어요
	public function activate_plgin() {
		$this->make_dir();
	}

	# post_type들을 가져와서 메타박스를 표시해요
	public function set_meta_box() {
		$post_types = get_post_types();

		unset($post_types["attachment"]);
		unset($post_types["revision"]);
		unset($post_types["nav_menu_item"]);

		foreach($post_types as $type) {
			add_meta_box("sj_metabox_html_to_post", __("Choose External HTML", $this->text_domain), array(&$this, "meta_box"), $type);
		}
	}

	# 메타박스를 저장해요
	public function save_post($post_id) {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return true;

		if (!current_user_can('edit_page', $post_id))
			return true;

		update_post_meta($post_id, 'sj-html-to-post-shortcode', isset($_POST["shortcode"]));

		if ( !empty( $_POST[$this->text_domain . "_HTML"] ) && $_POST[$this->text_domain . "_HTML"] == "unset" ) {
			delete_post_meta($post_id, 'sj-html-to-post');
		} else {
			if ( !empty( $_POST[$this->text_domain . "_HTML"] ) ) {
				update_post_meta($post_id, 'sj-html-to-post', $_POST[$this->text_domain . "_HTML"]);
			}
		}

		if ( !empty( $_POST[$this->text_domain . "_CSS"] ) && $_POST[$this->text_domain . "_CSS"] == "unset" ) {
			delete_post_meta($post_id, 'sj-css-to-post');
		} else {
			if ( !empty( $_POST[$this->text_domain . "_CSS"] ) ) {
				update_post_meta($post_id, 'sj-css-to-post', $_POST[$this->text_domain . "_CSS"]);
			}
		}

		if ( !empty( $_POST[$this->text_domain . "_JS"] ) && $_POST[$this->text_domain . "_JS"] == "unset" ) {
			delete_post_meta($post_id, 'sj-js-to-post');
		} else {
			if ( !empty( $_POST[$this->text_domain . "_JS"] ) ) {
				update_post_meta($post_id, 'sj-js-to-post', $_POST[$this->text_domain . "_JS"]);
			}
		}
	}

	# HTML로 바꿔요
	public function replace_with_html($content) {
		global $post;

		if ((is_single() || is_page()) && get_post_meta($post->ID, "sj-html-to-post", true) && !get_post_meta($post->ID, 'sj-html-to-post-shortcode', true)) {
			$content_ = $this->make_html_from_file($post);
			if ($content_)
				$content = $content_;
		}

		return $content;
	}

	# HTML을 만들어요

	private function make_html_from_file($post) {
		$file = get_post_meta($post->ID, "sj-html-to-post", true);
		$content = false;

		if (file_exists($this->dir_html . "/" . $file)) {
			$handle = fopen($this->dir_html . "/" . $file, "r");
			$content = stream_get_contents($handle);
			fclose($handle);

			$content = force_balance_tags($content);
			$content = str_replace(']]>', ']]&gt;', $content);
			$content = wpautop($content);
			$content = do_shortcode($content);
		}

		return $content;
	}

	public function wp_enqueue_scripts() {
		if ((is_single() || is_page()) && !is_admin()) {
			global $post;
			$dir = wp_upload_dir();

			if ($file = get_post_meta($post->ID, "sj-css-to-post", true)) {
				wp_enqueue_style("sj-css-to-post", $dir['baseurl'] . "/sujin/html2post/css/" . $file);
			}

			if ($file = get_post_meta($post->ID, "sj-js-to-post", true)) {
				wp_enqueue_script("sj-js-to-post", $dir['baseurl'] . "/sujin/html2post/js/" . $file);
			}
		}
	}

	public function admin_enqueue_scripts() {
		wp_enqueue_style("html2post", plugin_dir_url( __FILE__ ) . "style.css");
		add_thickbox();
	}

	private function get_dir() {
		$dir = wp_upload_dir();

		$this->dir = $dir['basedir'] . "/sujin";
		$this->dir_plugin = $this->dir . "/html2post";
		$this->dir_html = $this->dir_plugin . "/html";
		$this->dir_css = $this->dir_plugin . "/css";
		$this->dir_script = $this->dir_plugin . "/js";
	}

	# dir을 만들어요
	private function make_dir() {
		# 디렉토리 생성
		$this->mkdir($this->dir);
		$this->mkdir($this->dir_plugin);
		$this->mkdir($this->dir_html);
		$this->mkdir($this->dir_css);
		$this->mkdir($this->dir_script);
	}

	private function mkdir($dir) {
		if (!is_dir($dir)) {
			if (mkdir($dir, 0777, true)) {
				chmod($dir, 0777);
				return $dir;
			}
			return false;
		}

		return $dir;
	}

	# 인스턴스를 생성해요
	public static function getInstance() {
		if (!self::$instance)
			self::$instance = new self;

		return self::$instance;
	}

	# 메타박스를 표시해요
	public function meta_box($post) {
		# HTML : dir 속의 파일들을 읽어요
		$handle = opendir($this->dir_html);
		$files = array();

		while (false !== ($entry = readdir($handle))) {
			if ($entry == '.' || $entry == '..') {
				continue;
			}

			$files[] = $entry;
		}

		$meta = get_post_meta($post->ID, "sj-html-to-post", true);
		$meta_sc = get_post_meta($post->ID, "sj-html-to-post-shortcode", true);
		$meta_sc_text = __("Check if you want to display as shortcode (<code>[html2post /]</code>)", $this->text_domain);
		$selected_meta_sc = ($meta_sc) ? 'checked="checked"' : "";

		?>

			<div id="h2-wrapper">
				<h2>HTML</h2>
				<div id="use_shortcode">
					<input type="checkbox" id="shortcode" name="shortcode" value="true" <?php echo $selected_meta_sc ?> />
					<label for="shortcode"><?php echo $meta_sc_text ?></label>
				</div>
			</div>

			<ul id="sjHTML-wrapper">

		<?php

		if ($files) {
			$selected_unset = (!$meta) ? 'checked="checked"' : "";
			$unset_text = __("Check if you want to disable this option", $this->text_domain);

			?>

				<li>
					<input type="radio" id="unset_HTML" name="<?php echo $this->text_domain ?>_HTML" value="unset" <?php echo $selected_unset ?> />
					<label for="unset_HTML" class="unset"><?php echo $unset_text ?></label>
				</li>

			<?php

			foreach($files as $id => $file) {
				$selected = ($file == $meta) ? 'checked="checked"' : "";

				?>

				<li>
					<input type="radio" id="<?php echo $this->text_domain ?>_<?php echo $id ?>_HTML" name="<?php echo $this->text_domain ?>_HTML" value="<?php echo $file ?>" <?php echo $selected ?> />
					<label for="<?php echo $this->text_domain ?>_<?php echo $id ?>_HTML"><?php echo $file ?></label>
				</li>

				<?php

			}
		} else {

			?>

				<li class="no-files">
					<?php printf(__("There are no file exists in <code>%s</code>. You must make one via ftp.", $this->text_domain), $this->dir_html) ?>
				</li>

			<?php
		}

		?>

			</ul>
			<div class='clear'></div>

		<?php

		# CSS : dir 속의 파일들을 읽어요
		$handle = opendir($this->dir_css);
		$files = array();

		while (false !== ($entry = readdir($handle))) {
			if ($entry == '.' || $entry == '..') {
				continue;
			}

			$files[] = $entry;
		}

		$meta = get_post_meta($post->ID, "sj-css-to-post", true);

		?>
			<h2>CSS</h2>
			<ul id="sjCSS-wrapper">

		<?php

		if ($files) {
			$selected_unset = (!$meta) ? 'checked="checked"' : "";
			$unset_text = __("Check if you want to disable this option", $this->text_domain);

			?>

				<li>
					<input type="radio" id="unset_CSS" name="<?php echo $this->text_domain ?>_CSS" value="unset" <?php echo $selected_unset ?> />
					<label for="unset_CSS" class="unset"><?php echo $unset_text ?></label>
				</li>

			<?php

			foreach($files as $id => $file) {
				$selected = ($file == $meta) ? 'checked="checked"' : "";

				?>

				<li>
					<input type="radio" id="<?php echo $this->text_domain ?>_<?php echo $id ?>_CSS" name="<?php echo $this->text_domain ?>_CSS" value="<?php echo $file ?>" <?php echo $selected ?> />
					<label for="<?php echo $this->text_domain ?>_<?php echo $id ?>_CSS"><?php echo $file ?></label>
				</li>

				<?php

			}
		} else {
			?>

				<li class="no-files">
					<?php printf(__("There are no file exists in <code>%s</code>. You must make one via ftp.", $this->text_domain), $this->dir_css) ?>
				</li>

			<?php
		}

		?>

			</ul>
			<div class='clear'></div>

		<?php

		# JS : dir 속의 파일들을 읽어요
		$handle = opendir($this->dir_script);
		$files = array();

		while (false !== ($entry = readdir($handle))) {
			if ($entry == '.' || $entry == '..') {
				continue;
			}

			$files[] = $entry;
		}

		$meta = get_post_meta($post->ID, "sj-js-to-post", true);

		?>
			<h2>JS</h2>
			<ul id="sjJS-wrapper">

		<?php

		if ($files) {
			$selected_unset = (!$meta) ? 'checked="checked"' : "";
			$unset_text = __("Check if you want to disable this option", $this->text_domain);

			?>

				<li>
					<input type="radio" id="unset_JS" name="<?php echo $this->text_domain ?>_JS" value="unset" <?php echo $selected_unset ?> />
					<label for="unset_JS" class="unset"><?php echo $unset_text ?></label>
				</li>

			<?php

			foreach($files as $id => $file) {
				$selected = ($file == $meta) ? 'checked="checked"' : "";

				?>

				<li>
					<input type="radio" id="<?php echo $this->text_domain ?>_<?php echo $id ?>_JS" name="<?php echo $this->text_domain ?>_JS" value="<?php echo $file ?>" <?php echo $selected ?> />
					<label for="<?php echo $this->text_domain ?>_<?php echo $id ?>_JS"><?php echo $file ?></label>
				</li>

				<?php

			}
		} else {
			?>

				<li class="no-files">
					<?php printf(__("There are no file exists in <code>%s</code>. You must make one via ftp.", $this->text_domain), $this->dir_script) ?>
				</li>

			<?php
		}

		echo "</ul>";
	}
}

$sjHtmlToPost = sjHtmlToPost::getInstance();