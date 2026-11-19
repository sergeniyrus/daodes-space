<?php

namespace App\Http\Controllers;

use App\Models\OrderReview;
use App\Models\Organization;
use App\Models\OrganizationFeedback;
use App\Support\FeedbackNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrganizationFeedbackController extends Controller
{
    public function index(Request $request, Organization $organization): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:new,in_progress,answered,closed'],
            'type' => ['nullable', 'in:review,suggestion,complaint,thanks'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
        ]);

        $reviews = OrderReview::query()->with(['customer', 'order'])
            ->where('organization_id', $organization->id)
            ->when($filters['rating'] ?? null, fn ($query, $rating) => $query->where('rating', $rating))
            ->latest()->paginate(20, ['*'], 'reviews_page')->withQueryString();

        $newFeedbackCount = OrganizationFeedback::query()->where('organization_id', $organization->id)->where('status', 'new')->count();

        $feedback = OrganizationFeedback::query()->with(['customer', 'order'])
            ->where('organization_id', $organization->id)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->latest()->paginate(20, ['*'], 'feedback_page')->withQueryString();

        $ratingStats = OrderReview::query()->where('organization_id', $organization->id)
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->first();

        $ratingDistribution = OrderReview::query()->where('organization_id', $organization->id)
            ->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating');

        return view('organizations.feedback.index', compact('organization', 'reviews', 'feedback', 'ratingStats', 'ratingDistribution', 'newFeedbackCount', 'filters'));
    }

    public function reply(Request $request, Organization $organization, OrganizationFeedback $feedback): RedirectResponse
    {
        abort_unless((int) $feedback->organization_id === (int) $organization->id, 404);
        $validated = $request->validate([
            'reply' => ['required', 'string', 'min:2', 'max:5000'],
            'status' => ['required', 'in:answered,closed'],
        ]);

        $feedback->update([
            'reply' => $validated['reply'],
            'status' => $validated['status'],
            'replied_by_user_id' => Auth::id(),
            'replied_by_employee_id' => null,
            'replied_at' => now(),
            'closed_at' => $validated['status'] === 'closed' ? now() : null,
        ]);

        app(FeedbackNotificationService::class)->notifyCustomerAboutReply($feedback);

        return back()->with('success', 'Ответ отправлен клиенту.');
    }

    public function moderateReview(Request $request, Organization $organization, OrderReview $review): RedirectResponse
    {
        abort_unless((int) $review->organization_id === (int) $organization->id, 404);
        $validated = $request->validate(['is_public' => ['required', 'boolean']]);
        $review->update([
            'is_public' => (bool) $validated['is_public'],
            'published_at' => $validated['is_public'] ? ($review->published_at ?? now()) : null,
        ]);
        return back()->with('success', $validated['is_public'] ? 'Отзыв опубликован.' : 'Отзыв скрыт.');
    }
}
