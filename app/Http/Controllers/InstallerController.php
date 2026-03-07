<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use dacoto\EnvSet\Facades\EnvSet;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Request as RequestFacades;
use dacoto\LaravelWizardInstaller\Controllers\InstallFolderController;
use dacoto\LaravelWizardInstaller\Controllers\InstallServerController;

class InstallerController extends Controller {
    public function purchaseCodeIndex() {
        if ((new InstallServerController())->check() === false || (new InstallFolderController())->check() === false) {
            return redirect()->route('install.folders');
        }
        return view('vendor.installer.steps.purchase-code');
    }


    public function checkPurchaseCode(Request $request) {
        try {
            // OMKO: Skip purchase code validation for development
            $purchaseCode = $request->input('purchase_code') ?: 'omko-development-bypass';
            
            EnvSet::setKey('APPSECRET', $purchaseCode);
            EnvSet::save();

            $envUpdates = [
                'APP_URL' => RequestFacades::root(),
            ];
            updateEnv($envUpdates);

            return redirect()->route('install.database');
        } catch (Exception $e) {
            $values = [
                'purchase_code' => $request->get("purchase_code"),
            ];
            return view('vendor.installer.steps.purchase-code', ['values' => $values, 'error' => $e->getMessage()]);
        }
    }

}
