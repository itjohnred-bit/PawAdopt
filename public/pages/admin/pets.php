<?php
require_once __DIR__ . '/../../../includes/functions.php';
requireRole('ADMIN');
$pageTitle = 'Pet Listings';
$user = getCurrentUser();
$db   = Database::getInstance();

$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['search'] ?? '');

$where  = []; $params = [];
if ($statusFilter) { $where[] = 'p.status = ?'; $params[] = $statusFilter; }
if ($search)       { $where[] = '(p.name LIKE ? OR p.breed LIKE ? OR sp.shelter_name LIKE ?)'; $params = array_merge($params,["%$search%","%$search%","%$search%"]); }
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';

$pets = $db->fetchAll(
    "SELECT p.*, sp.shelter_name, sp.city as shelter_city,
     (SELECT pp.photo_url FROM pet_photos pp WHERE pp.pet_id = p.pet_id AND pp.is_primary = 1 LIMIT 1) as primary_photo
     FROM pets p JOIN shelter_profiles sp ON p.shelter_id = sp.shelter_id
     $whereStr ORDER BY p.created_at DESC",
    $params
);

include __DIR__ . '/../../../includes/header.php';
?>

<div class="flex text-slate-800 bg-slate-50 min-h-screen">
    
    <aside class="w-64 bg-white border-r h-screen sticky top-0 hidden md:block">
        <div class="p-6 border-b">
            <h1 class="text-teal-600 font-bold text-xl"><i class="fas fa-paw mr-2"></i>Paw-Adopt</h1>
        </div>
        <nav class="p-4 space-y-2">
            <a href="/pages/admin/dashboard.php" class="flex items-center p-3 text-slate-600 hover:bg-slate-50 rounded-lg"><i class="fas fa-th-large w-8"></i> Dashboard</a>
            <a href="/pages/admin/users.php" class="flex items-center p-3 text-slate-600 hover:bg-slate-50 rounded-lg"><i class="fas fa-users w-8"></i> Users</a>
            <a href="/pages/admin/pets.php" class="flex items-center p-3 bg-teal-50 text-teal-700 font-medium rounded-lg"><i class="fas fa-dog w-8"></i> Pets</a>
            <a href="/pages/admin/audit-logs.php" class="flex items-center p-3 text-slate-600 hover:bg-slate-50 rounded-lg"><i class="fas fa-history w-8"></i> Audit Logs</a>
        </nav>
    </aside>

    <main class="flex-1 p-8 overflow-y-auto">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h2 class="text-2xl font-bold">Pet Listings Management</h2>
                <p class="text-slate-500 text-sm mt-1"><?= count($pets) ?> active profile<?= count($pets) !== 1 ? 's' : '' ?> registered</p>
            </div>
        </div>

        <form method="GET" class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex flex-col md:flex-row gap-3 items-center mb-8">
            <div class="relative flex-1 w-full">
                <i class="fas fa-search text-slate-400 absolute left-4 top-3.5"></i>
                <input type="text" name="search" class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-11 pr-4 py-2.5 text-sm focus:outline-none focus:border-teal-500 transition" placeholder="Search pets, breeds, or shelter locations…" value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <div class="w-full md:w-48">
                <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-teal-500 transition" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach (['Available','Pending','Adopted','Removed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-2 w-full md:w-auto justify-end">
                <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white px-5 py-2.5 rounded-lg text-sm font-semibold transition flex items-center justify-center">
                    <i class="fas fa-filter md:mr-2"></i> <span class="hidden md:inline">Filter</span>
                </button>
                <?php if ($search || $statusFilter): ?>
                    <a href="?" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-5 py-2.5 rounded-lg text-sm font-semibold transition flex items-center justify-center">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider w-20">Photo</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Species / Breed</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Shelter Affiliate</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Age</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Date Posted</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($pets)): ?>
                            <?php foreach ($pets as $pet):
                                $photo = $pet['primary_photo'] ? APP_URL.'/'.$pet['primary_photo'] : APP_URL.'/assets/images/pet-placeholder.png';
                            ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <img src="<?= $photo ?>" class="w-12 h-12 object-cover rounded-lg border border-slate-100 shadow-sm" onerror="this.src='<?= APP_URL ?>/assets/images/pet-placeholder.png'" alt="">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-800"><?= sanitize($pet['name']) ?></div>
                                    <div class="text-xs text-slate-400">ID: #<?= $pet['pet_id'] ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                    <span class="font-medium text-slate-700"><?= htmlspecialchars($pet['species']) ?></span>
                                    <?= $pet['breed'] ? ' <span class="text-slate-400">/</span> <span class="text-xs bg-slate-100 px-2 py-0.5 rounded text-slate-600">'.sanitize($pet['breed']).'</span>' : '' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                    <div class="font-medium text-slate-700"><?= sanitize($pet['shelter_name']) ?></div>
                                    <div class="text-xs text-slate-400"><i class="fas fa-map-marker-alt mr-1"></i><?= $pet['shelter_city'] ? sanitize($pet['shelter_city']) : 'Not Specified' ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 font-medium">
                                    <?= formatAge($pet['age_months']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?= statusBadge($pet['status']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400 font-mono">
                                    <?= date('M j, Y', strtotime($pet['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if ($pet['status'] !== 'Removed'): ?>
                                        <button onclick="removePet(<?= $pet['pet_id'] ?>)" class="bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1.5 rounded-md font-semibold transition text-xs">
                                            <i class="fas fa-trash-alt mr-1"></i> Remove
                                        </button>
                                    <?php else: ?>
                                        <span class="text-slate-400 bg-slate-50 px-2.5 py-1.5 rounded-md border border-slate-100 text-xs inline-block">Removed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-16 text-center text-slate-400">
                                    <div class="text-lg mb-1">🐾 No matching animal profiles discovered</div>
                                    <div class="text-xs text-slate-400">Modify your search query text or adjust filters.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
async function removePet(petId) {
    confirmAction('Remove this pet listing? It will be hidden from adopters.', async () => {
        const res = await apiRequest(window.BASE_URL + '/api/admin.php', 'POST', new URLSearchParams({
            action: 'remove_pet',
            pet_id: petId
        }));

        if (res.success) {
            showToast('Pet profile successfully archived.');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(res.message, 'error');
        }
    });
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>