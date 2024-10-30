<?php
if (!class_exists('Framework_Sujin_Plugin')) {
	class Framework_Sujin_Plugin {
		public $debug = false;
		private $debug_message;
	
		public $text_domain;
		public $name;
	
		protected function __construct() {
			add_action('wp_footer', array(&$this, 'print_error_message'));
			add_action('admin_footer', array(&$this, 'print_error_message'));
		}
	
		private function redirect($url) {
			if (!$url) $url = $_SERVER['HTTP_REFERER'];
	
			if (function_exists("wp_redirect")) {
				wp_redirect($url);
				die;
			}
	
			echo '<script>window.location="' . $url . '"</script>';
			die;
		}
	
		protected function d($array) {
			$this->debug_message[] = $array;
		}
	
		public function print_error_message() {
			if ($this->debug_message) {
				if ($this->debug == false && !is_admin())
					return false;
	
				$style = (!is_admin()) ? '' : 'style="display:none;"';
	
				echo "<div $style>";
	
				$i = 1;
	
				foreach($this->debug_message as $debug) {
					echo "<h1>Debug Message $i</h1>";
					echo "<pre>";
					print_r($debug);
					echo "</pre>";
					
					$i++;
				}
	
				echo "</div>";
			}
		}
	}
}
