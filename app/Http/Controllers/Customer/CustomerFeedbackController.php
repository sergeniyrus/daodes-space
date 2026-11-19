<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReview;
use App\Models\Organization;
use App\Models\OrganizationFeedback;
use App\Support\FeedbackNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerFeedbackController extends Controller
{
    private const REVIEWABLE_STATUSES = [
        Order::STATUS_ISSUED,
        Order::STATUS_SERVED,
        Order::STATUS_DELIVERED,
        Order::STATUS_COMPLETED,
    ];

    public function index(): View|RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        if (!$customer) return redirect()->guest(route('customer.login'));

        $feedback = $customer->id
            ? OrganizationFeedback::query()->with('organization')->where('customer_id', $customer->id)->latest()->paginate(15)
            : collect();

        $reviews = OrderReview::query()->with(['organization', 'order'])->where('customer_id', $customer->id)->latest()->paginate(15, ['*'], 'reviews_page');

        $selectedOrganization = null;
        if (request()->filled('organization')) {
            $selectedOrganization = Organization::query()
                ->where('status', 'active')
                ->findOrFail((int) request()->query('organization'));
        }

        $organizations = $selectedOrganization
            ? collect()
            : Organization::query()->where('status', 'active')->orderBy('name')->get();

        $customerOrdersQuery = $customer->orders()->with('organization');
        if ($selectedOrganization) {
            $customerOrdersQuery->where('organization_id', $selectedOrganization->id);
        }
        $customerOrders = $customerOrdersQuery->latest()->limit(100)->get();

        return view('customer.feedback.index', compact(
            'feedback',
            'reviews',
            'organizations',
            'customerOrders',
            'selectedOrganization'
        ));
    }

    public function publicReviews(Organization $organization): View
    {
        abort_unless($organization->isActive(), 404);

        $reviews = OrderReview::query()->with('customer')
            ->where('organization_id', $organization->id)
            ->where('is_public', true)
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->paginate(20);

        $ratingStats = OrderReview::query()->where('organization_id', $organization->id)
            ->where('is_public', true)
            ->whereNotNull('published_at')
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')->first();

        return view('customer.feedback.public', compact('organization', 'reviews', 'ratingStats'));
    }

    public function storeReview(\Illuminate\Http\Request $request, Order $order): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        if (!$customer) return redirect()->guest(route('customer.login'));

        abort_unless((int) $order->customer_id === (int) $customer->id, 404);
        abort_unless(in_array($order->status, self::REVIEWABLE_STATUSES, true), 403, 'Оценить можно только полученный заказ.');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:3000'],
        ]);

        OrderReview::query()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'organization_id' => $order->organization_id,
                'customer_id' => $customer->id,
                'rating' => $validated['rating'],
                'comment' => trim((string) ($validated['comment'] ?? '')) ?: null,
                'is_public' => false,
                'published_at' => null,
            ]
        );

        return redirect()->route('customer.orders.show', $order)->with('success', 'Спасибо! Ваша оценка сохранена.');
    }

    public function storeFeedback(\Illuminate\Http\Request $request, Organization $organization): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();
        if (!$customer) return redirect()->guest(route('customer.login'));

        abort_unless($organization->isActive(), 404);

        $validated = $request->validate([
            'type' => ['required', 'in:review,suggestion,complaint,thanks'],
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'order_id' => ['nullable', 'integer'],
        ]);

        $order = null;
        if (!empty($validated['order_id'])) {
            $order = $customer->orders()->where('organization_id', $organization->id)->whereKey($validated['order_id'])->firstOrFail();
        }

        DB::transaction(function () use ($organization, $customer, $validated, $order) {
            $feedback = OrganizationFeedback::query()->create([
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'order_id' => $order?->id,
                'type' => $validated['type'],
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'status' => 'new',
            ]);
            app(FeedbackNotificationService::class)->notifyOrganizationAboutNewFeedback($feedback);
        });

        return redirect()->route('customer.feedback.index')->with('success', 'Обращение отправлено. Ответ появится в разделе «Мои отзывы».');
    }
}
