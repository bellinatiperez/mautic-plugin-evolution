<?php
/**
 * @var \Mautic\CoreBundle\Templating\Helper\AssetsHelper $view['assets']
 * @var \Mautic\CoreBundle\Templating\Helper\SlotsHelper $view['slots']
 * @var \Symfony\Component\Form\FormView $form
 * @var array $config
 * @var array $webhookStatus
 * @var array $connectionStatus
 */

$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'evolutionConfig');
$view['slots']->set('headerTitle', $view['translator']->trans('mautic.evolution.config.title'));

// Load required assets
$view['assets']->addScript('plugins/EvolutionAnalyticsBundle/Assets/js/config.js');
$view['assets']->addStylesheet('plugins/EvolutionAnalyticsBundle/Assets/css/config.css');
?>

<div class="evolution-config">
    <!-- Connection Status -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="panel panel-<?php echo $connectionStatus['status'] === 'connected' ? 'success' : 'danger'; ?>">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="fa fa-<?php echo $connectionStatus['status'] === 'connected' ? 'check-circle' : 'times-circle'; ?>"></i>
                        <?php echo $view['translator']->trans('mautic.evolution.config.connection_status'); ?>
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-8">
                            <p class="mb-1">
                                <strong><?php echo $view['translator']->trans('mautic.evolution.config.status'); ?>:</strong>
                                <span class="label label-<?php echo $connectionStatus['status'] === 'connected' ? 'success' : 'danger'; ?>">
                                    <?php echo $view['translator']->trans('mautic.evolution.config.status.' . $connectionStatus['status']); ?>
                                </span>
                            </p>
                            <?php if (!empty($connectionStatus['message'])): ?>
                                <p class="mb-1">
                                    <strong><?php echo $view['translator']->trans('mautic.evolution.config.message'); ?>:</strong>
                                    <?php echo $connectionStatus['message']; ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($connectionStatus['last_check'])): ?>
                                <p class="mb-0">
                                    <strong><?php echo $view['translator']->trans('mautic.evolution.config.last_check'); ?>:</strong>
                                    <?php echo date('Y-m-d H:i:s', $connectionStatus['last_check']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-right">
                            <button type="button" id="test-connection" class="btn btn-primary">
                                <i class="fa fa-refresh"></i> <?php echo $view['translator']->trans('mautic.evolution.config.test_connection'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Form -->
    <div class="row">
        <div class="col-md-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.evolution.config.settings'); ?></h3>
                </div>
                <div class="panel-body">
                    <?php echo $view['form']->start($form); ?>
                    
                    <!-- API Configuration -->
                    <fieldset>
                        <legend><?php echo $view['translator']->trans('mautic.evolution.config.api_settings'); ?></legend>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['api_url']); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['api_key']); ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['instance_name']); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['timeout']); ?>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Webhook Configuration -->
                    <fieldset>
                        <legend><?php echo $view['translator']->trans('mautic.evolution.config.webhook_settings'); ?></legend>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <?php echo $view['form']->row($form['webhook_enabled']); ?>
                            </div>
                        </div>
                        
                        <div id="webhook-settings" style="<?php echo $form['webhook_enabled']->vars['data'] ? '' : 'display: none;'; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <?php echo $view['form']->row($form['webhook_url']); ?>
                                </div>
                                <div class="col-md-6">
                                    <?php echo $view['form']->row($form['webhook_secret']); ?>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <?php echo $view['form']->row($form['webhook_events']); ?>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Synchronization Settings -->
                    <fieldset>
                        <legend><?php echo $view['translator']->trans('mautic.evolution.config.sync_settings'); ?></legend>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['sync_contacts']); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['sync_messages']); ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['sync_interval']); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['batch_size']); ?>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Analytics Settings -->
                    <fieldset>
                        <legend><?php echo $view['translator']->trans('mautic.evolution.config.analytics_settings'); ?></legend>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['analytics_enabled']); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['analytics_retention_days']); ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['track_message_types']); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['track_response_time']); ?>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Advanced Settings -->
                    <fieldset>
                        <legend><?php echo $view['translator']->trans('mautic.evolution.config.advanced_settings'); ?></legend>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['debug_mode']); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['log_level']); ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['rate_limit']); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo $view['form']->row($form['retry_attempts']); ?>
                            </div>
                        </div>
                    </fieldset>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> <?php echo $view['translator']->trans('mautic.evolution.config.save'); ?>
                        </button>
                        
                        <button type="button" id="reset-config" class="btn btn-warning">
                            <i class="fa fa-refresh"></i> <?php echo $view['translator']->trans('mautic.evolution.config.reset'); ?>
                        </button>
                        
                        <a href="<?php echo $view['router']->path('mautic_evolution_analytics_index'); ?>" class="btn btn-default">
                            <i class="fa fa-bar-chart"></i> <?php echo $view['translator']->trans('mautic.evolution.config.view_analytics'); ?>
                        </a>
                    </div>

                    <?php echo $view['form']->end($form); ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar with Information -->
        <div class="col-md-4">
            <!-- Webhook Status -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.evolution.config.webhook_status'); ?></h3>
                </div>
                <div class="panel-body">
                    <?php if (!empty($webhookStatus)): ?>
                        <div class="list-group">
                            <?php foreach ($webhookStatus as $event => $status): ?>
                                <div class="list-group-item">
                                    <div class="row">
                                        <div class="col-xs-8">
                                            <strong><?php echo $event; ?></strong>
                                        </div>
                                        <div class="col-xs-4 text-right">
                                            <span class="label label-<?php echo $status['active'] ? 'success' : 'default'; ?>">
                                                <?php echo $status['active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if (!empty($status['last_received'])): ?>
                                        <small class="text-muted">
                                            Last: <?php echo date('Y-m-d H:i', $status['last_received']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted"><?php echo $view['translator']->trans('mautic.evolution.config.no_webhooks'); ?></p>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <button type="button" id="setup-webhooks" class="btn btn-sm btn-primary btn-block">
                            <i class="fa fa-cog"></i> <?php echo $view['translator']->trans('mautic.evolution.config.setup_webhooks'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.evolution.config.quick_stats'); ?></h3>
                </div>
                <div class="panel-body">
                    <div class="row text-center">
                        <div class="col-xs-6">
                            <h4 id="total-messages">-</h4>
                            <small><?php echo $view['translator']->trans('mautic.evolution.config.total_messages'); ?></small>
                        </div>
                        <div class="col-xs-6">
                            <h4 id="total-contacts">-</h4>
                            <small><?php echo $view['translator']->trans('mautic.evolution.config.total_contacts'); ?></small>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-xs-6">
                            <h4 id="today-messages">-</h4>
                            <small><?php echo $view['translator']->trans('mautic.evolution.config.today_messages'); ?></small>
                        </div>
                        <div class="col-xs-6">
                            <h4 id="active-instances">-</h4>
                            <small><?php echo $view['translator']->trans('mautic.evolution.config.active_instances'); ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Help & Documentation -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.evolution.config.help'); ?></h3>
                </div>
                <div class="panel-body">
                    <div class="list-group">
                        <a href="#" class="list-group-item" data-toggle="modal" data-target="#help-modal">
                            <i class="fa fa-question-circle"></i> <?php echo $view['translator']->trans('mautic.evolution.config.setup_guide'); ?>
                        </a>
                        <a href="#" class="list-group-item" onclick="window.open('https://evolution-api.com/docs', '_blank')">
                            <i class="fa fa-external-link"></i> <?php echo $view['translator']->trans('mautic.evolution.config.api_docs'); ?>
                        </a>
                        <a href="#" class="list-group-item" data-toggle="modal" data-target="#troubleshoot-modal">
                            <i class="fa fa-wrench"></i> <?php echo $view['translator']->trans('mautic.evolution.config.troubleshoot'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="help-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><?php echo $view['translator']->trans('mautic.evolution.config.setup_guide'); ?></h4>
            </div>
            <div class="modal-body">
                <h5><?php echo $view['translator']->trans('mautic.evolution.config.step1'); ?></h5>
                <p><?php echo $view['translator']->trans('mautic.evolution.config.step1_desc'); ?></p>
                
                <h5><?php echo $view['translator']->trans('mautic.evolution.config.step2'); ?></h5>
                <p><?php echo $view['translator']->trans('mautic.evolution.config.step2_desc'); ?></p>
                
                <h5><?php echo $view['translator']->trans('mautic.evolution.config.step3'); ?></h5>
                <p><?php echo $view['translator']->trans('mautic.evolution.config.step3_desc'); ?></p>
                
                <div class="alert alert-info">
                    <strong><?php echo $view['translator']->trans('mautic.evolution.config.note'); ?>:</strong>
                    <?php echo $view['translator']->trans('mautic.evolution.config.webhook_url_info'); ?>
                    <br>
                    <code><?php echo $view['router']->generate('mautic_evolution_webhook_receive', [], true); ?></code>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <?php echo $view['translator']->trans('mautic.evolution.config.close'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Troubleshoot Modal -->
<div class="modal fade" id="troubleshoot-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><?php echo $view['translator']->trans('mautic.evolution.config.troubleshoot'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="panel-group" id="troubleshoot-accordion">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <a data-toggle="collapse" data-parent="#troubleshoot-accordion" href="#connection-issues">
                                    <?php echo $view['translator']->trans('mautic.evolution.config.connection_issues'); ?>
                                </a>
                            </h4>
                        </div>
                        <div id="connection-issues" class="panel-collapse collapse in">
                            <div class="panel-body">
                                <ul>
                                    <li><?php echo $view['translator']->trans('mautic.evolution.config.check_api_url'); ?></li>
                                    <li><?php echo $view['translator']->trans('mautic.evolution.config.verify_api_key'); ?></li>
                                    <li><?php echo $view['translator']->trans('mautic.evolution.config.check_firewall'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <a data-toggle="collapse" data-parent="#troubleshoot-accordion" href="#webhook-issues">
                                    <?php echo $view['translator']->trans('mautic.evolution.config.webhook_issues'); ?>
                                </a>
                            </h4>
                        </div>
                        <div id="webhook-issues" class="panel-collapse collapse">
                            <div class="panel-body">
                                <ul>
                                    <li><?php echo $view['translator']->trans('mautic.evolution.config.webhook_url_accessible'); ?></li>
                                    <li><?php echo $view['translator']->trans('mautic.evolution.config.webhook_events_configured'); ?></li>
                                    <li><?php echo $view['translator']->trans('mautic.evolution.config.check_webhook_logs'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <?php echo $view['translator']->trans('mautic.evolution.config.close'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Configuration data for JavaScript
window.evolutionConfig = {
    testConnectionUrl: '<?php echo $view['router']->path('mautic_evolution_config_test'); ?>',
    setupWebhooksUrl: '<?php echo $view['router']->path('mautic_evolution_config_webhooks'); ?>',
    resetConfigUrl: '<?php echo $view['router']->path('mautic_evolution_config_reset'); ?>',
    quickStatsUrl: '<?php echo $view['router']->path('mautic_evolution_analytics_realtime'); ?>'
};
</script>