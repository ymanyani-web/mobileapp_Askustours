<?php
require 'includes/bootstrap.php'; require_admin(); require 'includes/layout.php';
$counts=[];
$queries=[
 'customers'=>"SELECT COUNT(*) FROM users WHERE role='customer'",
 'partners'=>"SELECT COUNT(*) FROM users WHERE role='partner' OR partner_intent=1",
 'reservations'=>"SELECT COUNT(*) FROM reservations",
 'pendingPartners'=>"SELECT COUNT(*) FROM partner_profiles WHERE status='submitted'",
 'tripRequests'=>"SELECT COUNT(*) FROM trip_requests WHERE status<>'draft'",
 'publishedServices'=>"SELECT (SELECT COUNT(*) FROM driver_services WHERE status='published')+(SELECT COUNT(*) FROM guide_services WHERE status='published')"
];
foreach($queries as $key=>$sql)$counts[$key]=(int)db()->query($sql)->fetchColumn();
$recentReservations=db()->query("SELECT r.id,r.service_type,r.status,r.payment_status,r.created_at,CONCAT(u.first_name,' ',u.last_name) customer_name FROM reservations r JOIN users u ON u.id=r.customer_user_id ORDER BY r.created_at DESC LIMIT 8")->fetchAll();
$partnerQueue=db()->query("SELECT p.user_id,p.status,p.submitted_at,CONCAT(u.first_name,' ',u.last_name) partner_name,u.email,u.phone FROM partner_profiles p JOIN users u ON u.id=p.user_id WHERE p.status IN ('submitted','draft') ORDER BY FIELD(p.status,'submitted','draft'),p.updated_at DESC LIMIT 6")->fetchAll();
page_header('Operations overview'); ?>
<section class="hero"><div><p class="eyebrow">LIVE OPERATIONS</p><h2>AskUs Tours<br>control center.</h2><p>Monitor customers, collaborators, service requests and itinerary enquiries.</p></div><div class="hero-orbit"><span>◎</span></div></section>
<section class="stat-grid">
<article class="stat"><div class="stat-icon purple">♙</div><div><p>Customers</p><strong><?=number_format($counts['customers'])?></strong><small>Registered accounts</small></div></article>
<article class="stat"><div class="stat-icon teal">◇</div><div><p>Collaborators</p><strong><?=number_format($counts['partners'])?></strong><small><?=$counts['pendingPartners']?> awaiting review</small></div></article>
<article class="stat"><div class="stat-icon orange">☷</div><div><p>Service requests</p><strong><?=number_format($counts['reservations'])?></strong><small>Driver and guide requests</small></div></article>
<article class="stat"><div class="stat-icon pink">⌖</div><div><p>Trip enquiries</p><strong><?=number_format($counts['tripRequests'])?></strong><small><?=$counts['publishedServices']?> published services</small></div></article>
</section>
<section class="dashboard-grid"><article class="panel span-2"><div class="panel-head"><div><p class="eyebrow">RECENT ACTIVITY</p><h3>Latest service requests</h3></div><a href="bookings.php">Manage →</a></div><div class="table-wrap"><table><thead><tr><th>Request</th><th>Customer</th><th>Service</th><th>Status</th><th>Created</th></tr></thead><tbody><?php foreach($recentReservations as $row):?><tr><td><strong><?=e(substr($row['id'],0,12))?></strong></td><td><?=e($row['customer_name'])?></td><td><?=e(ucfirst($row['service_type']))?></td><td><span class="badge <?=e($row['status'])?>"><?=e($row['status'])?></span><small><?=e($row['payment_status'])?></small></td><td><?=e(date('M j, Y H:i',strtotime($row['created_at'])))?></td></tr><?php endforeach;?><?php if(!$recentReservations):?><tr><td colspan="5" class="empty">No service requests yet.</td></tr><?php endif;?></tbody></table></div></article>
<article class="panel"><div class="panel-head"><div><p class="eyebrow">REVIEW QUEUE</p><h3>Collaborators</h3></div></div><div class="status-list"><?php foreach($partnerQueue as $row):?><div><span class="status-dot <?=$row['status']==='submitted'?'pending':''?>"></span><label><?=e($row['partner_name'])?><small><?=e($row['status'])?></small></label></div><?php endforeach;?><?php if(!$partnerQueue):?><p class="empty">No applications waiting.</p><?php endif;?></div><a href="people.php?view=collaborators" class="btn secondary wide">Open collaborators</a></article></section>
<?php page_footer();
