<?php

defined( 'ABSPATH' ) || exit;
defined( 'WP_CACHE' ) || exit;

use Prometheus\RenderTextFormat;
use Prometheus\CollectorRegistry;
use Prometheus\Storage;

class ASSE_Prometheus {

  protected $rewrite_rule       = 'metrics/?$';
  protected $prefix             = 'wp';
  protected $query_var          = 'metrics';
  protected $url                = '/metrics';
  protected $wp_layer           = null;
  protected $wp_project         = null;

  protected $metrics  = array();
  protected $labels   = array();

  private $renderer   = null;
  private $registry   = null;
  private $adapter    = null;

  public function __construct() {
    $this->adapter    = new Prometheus\Storage\InMemory();
    $this->registry   = new CollectorRegistry($this->adapter);
    $this->renderer   = new RenderTextFormat();

    $this->labels = array(
      'layer'     => strtolower( getenv( 'WP_LAYER' ) ),
      'project'   => strtolower( getenv( 'PROJECT' ) ),
      'env'       => strtolower( getenv( 'ENVIRONMENT' ) )
    );

    $this->metrics['user_sum']              = $this->registry->getOrRegisterGauge( $this->prefix, 'user_sum', 'it sets', array_keys( $this->labels ) );
    $this->metrics['plugins_active_sum']    = $this->registry->getOrRegisterGauge( $this->prefix, 'plugins_active_sum', 'it sets', array_keys( $this->labels ) );
    $this->metrics['articles_publish_sum']  = $this->registry->getOrRegisterGauge( $this->prefix, 'articles_publish_sum', 'it sets', array_keys( $this->labels ) );
    $this->metrics['articles_draft_sum']    = $this->registry->getOrRegisterGauge( $this->prefix, 'articles_draft_sum', 'it sets', array_keys( $this->labels ) );
    $this->metrics['attachments_sum']       = $this->registry->getOrRegisterGauge( $this->prefix, 'attachments_sum', 'it sets', array_keys( $this->labels ) );

    add_filter( 'query_vars', array( &$this, 'add_query_vars' ) );
    add_filter( 'redirect_canonical', array( &$this, 'prevent_redirect_canonical' ) );

    add_action( 'init', array( &$this, 'rewrites_init' ) );
    add_action( 'template_redirect', array( &$this, 'send_metrics' ) );
    // add_action( 'shutdown', array( $this, 'set_execution_time' ) );
  }

  public function rewrites_init() {
    add_rewrite_rule(
      $this->rewrite_rule,
      'index.php?' . $this->query_var . '=true',
      'top'
    );

    $rules  = get_option( 'rewrite_rules' );
    if ( ! isset( $rules[$this->rewrite_rule] ) ) {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }
  }

  public function add_query_vars( $vars ) {
    $vars[] = $this->query_var;
    return $vars;
  }

  public function set_metrics() {
    $this->metrics['user_sum']->set( count_users()['total_users'], array_values( $this->labels ) );
    $this->metrics['plugins_active_sum']->set( count( get_option('active_plugins') ), array_values( $this->labels ) );

    $count_posts = wp_count_posts();
    $this->metrics['articles_publish_sum']->set( $count_posts->publish, array_values( $this->labels ) );
    $this->metrics['articles_draft_sum']->set( $count_posts->draft, array_values( $this->labels ) );

    wp_die(wp_count_attachments( get_allowed_mime_types() ));

    $count_attachments = wp_count_attachments();
    $this->metrics['attachments_sum']->set( $count_posts->draft, array_values( $this->labels ) );

    $result = wp_cache_get( 'test' );
    if ( false === $result ) {
	    $result = 1;
	    wp_cache_set( 'test', $result );
    }

    wp_die($result);

    return true;
  }

  public function send_metrics() {

    $wpe_metrics = get_query_var( $this->query_var, false );
    if ( $wpe_metrics != true ) {
      return;
    }

    $this->set_metrics() || exit;
    $result = $this->renderer->render( $this->registry->getMetricFamilySamples() );

    header_remove();

    @header( 'Content-type: ' . RenderTextFormat::MIME_TYPE );
    echo $result;
    exit;
  }

  public function prevent_redirect_canonical( $redirect_url ) {
    return !! strpos( $redirect_url, $this->url );
  }

}

$asse_prometheus = new ASSE_Prometheus();
