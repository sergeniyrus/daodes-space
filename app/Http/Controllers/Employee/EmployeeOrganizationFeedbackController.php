<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\OrderReview;
use App\Models\Organization;
use App\Models\OrganizationEmployeeMembership;
use App\Models\OrganizationFeedback;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmployeeOrganizationFeedbackController extends Controller
{
    public function index(Organization $organization): View
    {
        $employee = Auth::guard('employee')->user();
        abort_unless($employee, 403);
        OrganizationEmployeeMembership::query()
            ->where('organization_id', $organization->id)
            ->where('employee_id', $employee->id)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->firstOrFail();

        $reviews = OrderReview::query()->with(['customer', 'order'])->where('organization_id', $organization->id)->latest()->paginate(20, ['*'], 'reviews_page');
        $feedback = OrganizationFeedback::query()->with(['customer', 'order'])->where('organization_id', $organization->id)->latest()->paginate(20, ['*'], 'feedback_page');
        $ratingStats = OrderReview::query()->where('organization_id', $organization->id)->selectRaw('COUNT(*) as total, AVG(rating) as average')->first();
        $ratingDistribution = OrderReview::query()->where('organization_id', $organization->id)->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating');

        return view('organizations.feedback.admin-index', compact('organization', 'reviews', 'feedback', 'ratingStats', 'ratingDistribution'));
    }
}
