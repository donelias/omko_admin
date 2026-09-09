<?php

namespace App\Http\Controllers;

use App\Models\CrmInteraction;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CrmLeadController extends Controller
{
    protected $statuses = [
        'nuevo' => 'Nuevo',
        'contactado' => 'Contactado',
        'interesado' => 'Interesado',
        'calificado' => 'Calificado',
        'ganado' => 'Ganado',
        'perdido' => 'Perdido',
        'descartado' => 'Descartado',
    ];

    protected $origins = [
        'meta' => 'Meta Ads',
        'whatsapp' => 'WhatsApp',
        'formulario' => 'Formulario Web',
        'manual' => 'Manual',
        'otro' => 'Otro',
    ];

    /**
     * Vista principal del CRM: lista + embudo + reportes.
     */
    public function index()
    {
        if (! has_permissions('read', 'crm_leads') && ! has_permissions('read', 'crm_leads_reports')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $total = Lead::count();
        $byStatus = Lead::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->toArray();
        $byOrigin = Lead::selectRaw('origin, COUNT(*) as total')->groupBy('origin')->pluck('total', 'origin')->toArray();
        $byAgent = Lead::selectRaw('agent_id, COUNT(*) as total')->groupBy('agent_id')->pluck('total', 'agent_id')->toArray();

        $agents = Customer::where('is_agent', 1)->orderBy('name')->get(['id', 'name', 'email']);

        $funnel = [];
        foreach ($this->statuses as $key => $label) {
            $funnel[$key] = ['label' => $label, 'total' => $byStatus[$key] ?? 0];
        }

        $originSeries = [];
        foreach ($this->origins as $key => $label) {
            $originSeries[] = ['label' => $label, 'total' => $byOrigin[$key] ?? 0, 'key' => $key];
        }

        return view('admin.crm.leads.index', compact('total', 'funnel', 'originSeries', 'byAgent', 'agents'));
    }

    /**
     * Listado JSON para Bootstrap Table.
     */
    public function getLeadsList(Request $request)
    {
        if (! has_permissions('read', 'crm_leads')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $search = $request->input('search');
        $status = $request->input('status');
        $origin = $request->input('origin');
        $agentId = $request->input('agent_id');

        $query = Lead::query()->with(['agent', 'property']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }
        if ($origin && $origin !== 'all') {
            $query->where('origin', $origin);
        }
        if ($agentId && $agentId !== 'all') {
            $query->where('agent_id', $agentId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('crm_leads.nombre', 'LIKE', "%$search%")
                    ->orWhere('crm_leads.email', 'LIKE', "%$search%")
                    ->orWhere('crm_leads.telefono', 'LIKE', "%$search%")
                    ->orWhere('crm_leads.whatsapp', 'LIKE', "%$search%")
                    ->orWhereHas('property', function ($p) use ($search) {
                        $p->where('title', 'LIKE', "%$search%");
                    });
            });
        }

        $total = $query->count();

        $allowedSort = ['id', 'nombre', 'email', 'status', 'origin', 'score', 'created_at'];
        $sortColumn = in_array($sort, $allowedSort, true) ? $sort : 'id';
        $query->orderBy('crm_leads.'.$sortColumn, strtoupper($order) === 'ASC' ? 'ASC' : 'DESC');

        $leads = $query->skip($offset)->take($limit)->get();

        $rows = [];
        foreach ($leads as $lead) {
            $statusBadge = match ($lead->status) {
                'nuevo' => 'primary',
                'contactado' => 'info',
                'interesado' => 'warning',
                'calificado' => 'success',
                'ganado' => 'success',
                'perdido' => 'danger',
                'descartado' => 'secondary',
                default => 'secondary',
            };

            $operate = '';
            if (has_permissions('update', 'crm_leads')) {
                $operate .= '<a class="btn btn-primary btn-sm show-lead" data-id="'.$lead->id.'" title="'.__('View').'"><i class="bi bi-eye"></i></a> ';
                $operate .= '<button type="button" class="btn btn-success btn-sm change-status" data-id="'.$lead->id.'" data-status="calificado" title="'.__('Calificar').'"><i class="bi bi-check2-circle"></i></button>';
            }
            if (has_permissions('delete', 'crm_leads')) {
                $operate .= '<button type="button" class="btn btn-danger btn-sm delete-lead" data-id="'.$lead->id.'" title="'.__('Delete').'"><i class="bi bi-trash"></i></button>';
            }

            $rows[] = [
                'id' => $lead->id,
                'nombre' => $lead->nombre,
                'status_raw' => $lead->status,
                'contact' => ($lead->email ?? '').($lead->telefono ? '<br>'.$lead->telefono : ''),
                'property' => $lead->property?->title ?: '-',
                'agent' => $lead->agent?->name ?: '-',
                'status' => '<span class="badge bg-'.$statusBadge.'">'.($this->statuses[$lead->status] ?? $lead->status).'</span>',
                'origin' => $this->origins[$lead->origin] ?? $lead->origin,
                'score' => $lead->score,
                'created_at' => $lead->created_at ? $lead->created_at->format('d/m/Y H:i') : '-',
                'operate' => $operate,
            ];
        }

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    /**
     * Detalle de un lead (vista simple, usada por el modal del listado).
     */
    public function show(Request $request)
    {
        if (! has_permissions('read', 'crm_leads')) {
            return response()->json(['error' => true, 'message' => trans(PERMISSION_ERROR_MSG)]);
        }

        $lead = Lead::with(['property', 'agent', 'interactions', 'scoring'])->find($request->id);
        if (! $lead) {
            return response()->json(['error' => true, 'message' => __('Lead no encontrado')]);
        }

        return response()->json([
            'error' => false,
            'data' => [
                'nombre' => $lead->nombre,
                'email' => $lead->email,
                'telefono' => $lead->telefono,
                'whatsapp' => $lead->whatsapp,
                'property' => $lead->property?->title,
                'agent' => $lead->agent?->name,
                'status' => $this->statuses[$lead->status] ?? $lead->status,
                'origin' => $this->origins[$lead->origin] ?? $lead->origin,
                'score' => $lead->score,
                'recomendacion' => $lead->scoring?->recomendacion,
                'created_at' => $lead->created_at ? $lead->created_at->format('d/m/Y H:i') : '-',
                'interactions' => $lead->interactions->map(function ($i) {
                    return [
                        'type' => $i->type,
                        'contenido' => $i->contenido,
                        'resultado' => $i->resultado,
                        'created_at' => $i->created_at ? $i->created_at->format('d/m/Y H:i') : '-',
                    ];
                }),
            ],
        ]);
    }

    /**
     * Cambia el estado de un lead y registra la interacción.
     */
    public function updateStatus(Request $request)
    {
        if (! has_permissions('update', 'crm_leads')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $lead = Lead::find($request->id);
        if (! $lead) {
            return ResponseService::errorResponse(__('Lead no encontrado'));
        }

        $previous = $lead->status;
        $lead->status = $request->status;
        $lead->fecha_ultima_interaccion = now();
        $lead->save();

        CrmInteraction::create([
            'lead_id' => $lead->id,
            'agent_id' => $lead->agent_id,
            'type' => 'nota_interna',
            'contenido' => "Estado actualizado de {$previous} a {$lead->status} (admin)",
            'status_anterior' => $previous,
            'status_nuevo' => $lead->status,
        ]);

        return ResponseService::successResponse(__('Estado actualizado correctamente'));
    }

    /**
     * Elimina un lead (soft delete).
     */
    public function destroy(Request $request)
    {
        if (! has_permissions('delete', 'crm_leads')) {
            return ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $lead = Lead::find($request->id);
        if (! $lead) {
            return ResponseService::errorResponse(__('Lead no encontrado'));
        }

        $lead->delete();

        return ResponseService::successResponse(__('Lead eliminado correctamente'));
    }
}
