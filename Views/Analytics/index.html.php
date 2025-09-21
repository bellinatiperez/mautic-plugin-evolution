<?php
/**
 * @var \Mautic\CoreBundle\Templating\Helper\AssetsHelper $view['assets']
 * @var \Mautic\CoreBundle\Templating\Helper\SlotsHelper $view['slots']
 * @var array $data
 * @var array $filters
 * @var array $dateRange
 */

$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'evolutionAnalytics');
$view['slots']->set('headerTitle', $view['translator']->trans('mautic.evolution.analytics.title'));

// Load required assets
$view['assets']->addScript('plugins/EvolutionAnalyticsBundle/Assets/js/analytics.js');
$view['assets']->addStylesheet('plugins/EvolutionAnalyticsBundle/Assets/css/analytics.css');
$view['assets']->addScript('https://cdn.jsdelivr.net/npm/chart.js');
?>

<div class="evolution-analytics-dashboard">
    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.evolution.analytics.filters'); ?></h3>
                </div>
                <div class="panel-body">
                    <form id="analytics-filters" class="form-inline">
                        <div class="form-group mr-3">
                            <label for="instance_name"><?php echo $view['translator']->trans('mautic.evolution.analytics.instance'); ?>:</label>
                            <select id="instance_name" name="instance_name" class="form-control">
                                <option value=""><?php echo $view['translator']->trans('mautic.evolution.analytics.all_instances'); ?></option>
                                <?php foreach ($data['instance_stats'] as $instance): ?>
                                    <option value="<?php echo $instance['instance_name']; ?>" 
                                            <?php echo ($filters['instance_name'] ?? '') === $instance['instance_name'] ? 'selected' : ''; ?>>
                                        <?php echo $instance['instance_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group mr-3">
                            <label for="from_date"><?php echo $view['translator']->trans('mautic.evolution.analytics.from_date'); ?>:</label>
                            <input type="date" id="from_date" name="from_date" class="form-control" 
                                   value="<?php echo $dateRange['from']; ?>">
                        </div>
                        
                        <div class="form-group mr-3">
                            <label for="to_date"><?php echo $view['translator']->trans('mautic.evolution.analytics.to_date'); ?>:</label>
                            <input type="date" id="to_date" name="to_date" class="form-control" 
                                   value="<?php echo $dateRange['to']; ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <?php echo $view['translator']->trans('mautic.evolution.analytics.apply_filters'); ?>
                        </button>
                        
                        <button type="button" id="refresh-data" class="btn btn-default">
                            <i class="fa fa-refresh"></i> <?php echo $view['translator']->trans('mautic.evolution.analytics.refresh'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="panel panel-primary">
                <div class="panel-body text-center">
                    <h3 class="mb-0"><?php echo number_format($data['stats']['total_messages'] ?? 0); ?></h3>
                    <p class="mb-0"><?php echo $view['translator']->trans('mautic.evolution.analytics.total_messages'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="panel panel-success">
                <div class="panel-body text-center">
                    <h3 class="mb-0"><?php echo number_format($data['stats']['sent_messages'] ?? 0); ?></h3>
                    <p class="mb-0"><?php echo $view['translator']->trans('mautic.evolution.analytics.sent_messages'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="panel panel-info">
                <div class="panel-body text-center">
                    <h3 class="mb-0"><?php echo number_format($data['stats']['received_messages'] ?? 0); ?></h3>
                    <p class="mb-0"><?php echo $view['translator']->trans('mautic.evolution.analytics.received_messages'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="panel panel-warning">
                <div class="panel-body text-center">
                    <h3 class="mb-0"><?php echo number_format($data['stats']['unique_contacts'] ?? 0); ?></h3>
                    <p class="mb-0"><?php echo $view['translator']->trans('mautic.evolution.analytics.unique_contacts'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Daily Messages Chart -->
        <div class="col-md-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.evolution.analytics.daily_messages'); ?></h3>
                </div>
                <div class="panel-body">
                    <canvas id="dailyMessagesChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Message Types Chart -->
        <div class="col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.evolution.analytics.message_types'); ?></h3>
                </div>
                <div class="panel-body">
                    <canvas id="messageTypesChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Hourly Distribution Chart -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.evolution.analytics.hourly_distribution'); ?></h3>
                </div>
                <div class="panel-body">
                    <canvas id="hourlyDistributionChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row">
        <!-- Top Active Contacts -->
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.evolution.analytics.top_contacts'); ?></h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo $view['translator']->trans('mautic.evolution.analytics.contact'); ?></th>
                                    <th><?php echo $view['translator']->trans('mautic.evolution.analytics.phone'); ?></th>
                                    <th><?php echo $view['translator']->trans('mautic.evolution.analytics.messages'); ?></th>
                                    <th><?php echo $view['translator']->trans('mautic.evolution.analytics.last_activity'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['top_contacts'] as $contact): ?>
                                    <tr>
                                        <td>
                                            <?php if ($contact['firstname'] || $contact['lastname']): ?>
                                                <?php echo $contact['firstname'] . ' ' . $contact['lastname']; ?>
                                            <?php else: ?>
                                                <em><?php echo $view['translator']->trans('mautic.evolution.analytics.unknown_contact'); ?></em>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $contact['phone']; ?></td>
                                        <td>
                                            <span class="badge badge-primary"><?php echo $contact['message_count']; ?></span>
                                            <small class="text-muted">
                                                (<?php echo $contact['sent_count']; ?> sent, <?php echo $contact['received_count']; ?> received)
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo date('Y-m-d H:i', $contact['last_message_timestamp']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Instance Statistics -->
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.evolution.analytics.instance_stats'); ?></h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo $view['translator']->trans('mautic.evolution.analytics.instance'); ?></th>
                                    <th><?php echo $view['translator']->trans('mautic.evolution.analytics.messages'); ?></th>
                                    <th><?php echo $view['translator']->trans('mautic.evolution.analytics.contacts'); ?></th>
                                    <th><?php echo $view['translator']->trans('mautic.evolution.analytics.last_activity'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['instance_stats'] as $instance): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $instance['instance_name']; ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary"><?php echo $instance['message_count']; ?></span>
                                            <small class="text-muted">
                                                (<?php echo $instance['sent_messages']; ?> sent, <?php echo $instance['received_messages']; ?> received)
                                            </small>
                                        </td>
                                        <td><?php echo $instance['unique_contacts']; ?></td>
                                        <td>
                                            <?php if ($instance['last_activity']): ?>
                                                <?php echo date('Y-m-d H:i', $instance['last_activity']); ?>
                                            <?php else: ?>
                                                <em><?php echo $view['translator']->trans('mautic.evolution.analytics.no_activity'); ?></em>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Response Time Analytics -->
    <?php if (!empty($data['response_time'])): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.evolution.analytics.response_time'); ?></h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <h4><?php echo number_format($data['response_time']['avg_response_time'] / 60, 1); ?> min</h4>
                            <p><?php echo $view['translator']->trans('mautic.evolution.analytics.avg_response_time'); ?></p>
                        </div>
                        <div class="col-md-3 text-center">
                            <h4><?php echo number_format($data['response_time']['min_response_time'] / 60, 1); ?> min</h4>
                            <p><?php echo $view['translator']->trans('mautic.evolution.analytics.min_response_time'); ?></p>
                        </div>
                        <div class="col-md-3 text-center">
                            <h4><?php echo number_format($data['response_time']['max_response_time'] / 60, 1); ?> min</h4>
                            <p><?php echo $view['translator']->trans('mautic.evolution.analytics.max_response_time'); ?></p>
                        </div>
                        <div class="col-md-3 text-center">
                            <h4><?php echo number_format($data['response_time']['total_responses']); ?></h4>
                            <p><?php echo $view['translator']->trans('mautic.evolution.analytics.total_responses'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Export Options -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.evolution.analytics.export'); ?></h3>
                </div>
                <div class="panel-body">
                    <div class="btn-group">
                        <a href="<?php echo $view['router']->path('mautic_evolution_analytics_export', array_merge($filters, ['format' => 'csv', 'type' => 'messages'])); ?>" 
                           class="btn btn-default">
                            <i class="fa fa-download"></i> <?php echo $view['translator']->trans('mautic.evolution.analytics.export_messages_csv'); ?>
                        </a>
                        <a href="<?php echo $view['router']->path('mautic_evolution_analytics_export', array_merge($filters, ['format' => 'json', 'type' => 'messages'])); ?>" 
                           class="btn btn-default">
                            <i class="fa fa-download"></i> <?php echo $view['translator']->trans('mautic.evolution.analytics.export_messages_json'); ?>
                        </a>
                        <a href="<?php echo $view['router']->path('mautic_evolution_analytics_export', array_merge($filters, ['format' => 'csv', 'type' => 'contacts'])); ?>" 
                           class="btn btn-default">
                            <i class="fa fa-download"></i> <?php echo $view['translator']->trans('mautic.evolution.analytics.export_contacts_csv'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Pass data to JavaScript
window.evolutionAnalyticsData = {
    dailyStats: <?php echo json_encode($data['daily_stats']); ?>,
    messageTypes: <?php echo json_encode($data['message_types']); ?>,
    hourlyDistribution: <?php echo json_encode($data['hourly_distribution']); ?>,
    filters: <?php echo json_encode($filters); ?>
};
</script>