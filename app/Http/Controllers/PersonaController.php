<?php

namespace App\Http\Controllers;

use App\Enum\PersonaType;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PersonaController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'user_type' => ['required', Rule::enum(PersonaType::class)],
            'settings' => 'required|array',
        ]);

        // Validate settings based on user type
        $this->validateSettings($request->user_type, $request->settings);

        Auth::user()->personas()->create([
            'name' => $request->name,
            'user_type' => $request->user_type,
            'settings' => $request->settings,
        ]);

        return redirect()->route('mentions')->with('success', 'Persona created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Persona $persona)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'user_type' => ['required', Rule::enum(PersonaType::class)],
            'settings' => 'required|array',
        ]);

        // Validate settings based on user type
        $this->validateSettings($request->user_type, $request->settings);

        $persona->update([
            'name' => $request->name,
            'user_type' => $request->user_type,
            'settings' => $request->settings,
        ]);

        return redirect()->route('mentions')->with('success', 'Persona updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Persona $persona)
    {
        $persona->delete();

        return redirect()->route('mentions')->with('success', 'Persona deleted successfully.');
    }

    /**
     * Validate settings based on user type
     */
    private function validateSettings(string $userType, array $settings)
    {
        $rules = match ($userType) {
            PersonaType::SMALL_BUSINESS->value => [
                'business_name' => 'required|string|max:255',
                'business_description' => 'required|string|max:1000',
                'industry_niche' => 'required|string|max:255',
            ],
            PersonaType::MARKETING->value => [
                'brand_name' => 'required|string|max:255',
                'brand_description' => 'required|string|max:1000',
                'engagement_goal' => ['required', Rule::in(['brand_awareness', 'reputation_management', 'market_research'])],
            ],
            PersonaType::CONTENT_CREATOR->value => [
                'creator_niche' => 'required|string|max:255',
                'engagement_style' => ['required', Rule::in(['storytelling', 'question_asking', 'sharing_tips'])],
            ],
            PersonaType::CUSTOMER_SUPPORT->value => [
                'brand_name' => 'required|string|max:255',
                'brand_description' => 'required|string|max:1000',
                'product_service' => 'required|string|max:255',
                'support_contact' => 'required|string|max:255',
            ],
            PersonaType::MARKET_RESEARCHER->value => [
                'research_focus' => 'required|string|max:255',
                'question_style' => ['required', Rule::in(['open_ended', 'specific'])],
            ],
            PersonaType::FREELANCER->value => [
                'expertise_area' => 'required|string|max:255',
                'engagement_approach' => ['required', Rule::in(['offering_tips', 'answering_questions', 'sharing_experiences'])],
            ],
            PersonaType::PR_CRISIS->value => [
                'brand_name' => 'required|string|max:255',
                'brand_description' => 'required|string|max:1000',
                'escalation_contact' => 'required|string|max:255',
            ],
        };

        validator($settings, $rules)->validate();
    }
}
