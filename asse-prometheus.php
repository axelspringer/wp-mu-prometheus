<?php

defined( 'ABSPATH' ) || exit;
defined( 'WP_CACHE' ) || exit;

use Prometheus\RenderTextFormat;
use Prometheus\CollectorRegistry;
use Prometheus\Storage;

class ASSE_Prometheus {

  protected $endpoint_regex     = '^metrics/?';
  protected $endpoint_url       = 'metrics';
  protected $prefix             = 'wp';
  protected $query_var          = 'wpe_metrics';

  protected $metrics  = array();

  private $renderer   = null;
  private $registry   = null;
  private $adapter    = null;

  public function __construct() {
    $this->adapter    = new Prometheus\Storage\InMemory();
    $this->registry   = new CollectorRegistry($this->adapter);
    $this->renderer   = new RenderTextFormat();

    $this->metrics['user_sum']             = $this->registry->getOrRegisterGauge( $this->prefix, 'user_sum', 'it sets' );
    $this->metrics['plugins_active_sum']   = $this->registry->getOrRegisterGauge( $this->prefix, 'plugins_active_sum', 'it sets' );

    add_filter( 'query_vars', array( &$this, 'add_query_vars' ), 0 );

    add_action( 'init', array( &$this, 'rewrites_init' ) );
    add_action( 'template_redirect', array( &$this, 'send_metrics' ) );
    // add_action( 'shutdown', array( $this, 'set_execution_time' ) );
  }

  public function rewrites_init() {
    add_rewrite_rule(
      $this->endpoint_regex,
      'index.php?' . $this->query_var . '=true',
      'top'
    );
  }

  public function add_query_vars( $vars ) {
    $vars[] = $this->query_var;
    return $vars;
  }

  public function set_metrics() {
    $this->metrics['user_sum']->set( count_users()['total_users'] );
    $this->metrics['plugins_active_sum']->set( count( get_option('active_plugins') ) );
  }

  public function send_metrics() {
    $this->set_metrics();

    $result = $this->renderer->render($this->registry->getMetricFamilySamples());

    header_remove();

    @header( 'Content-type: ' . RenderTextFormat::MIME_TYPE );
    echo $result;
    exit;
  }

}

$asse_prometheus = new ASSE_Prometheus();
