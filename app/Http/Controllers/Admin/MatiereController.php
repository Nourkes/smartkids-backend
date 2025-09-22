<?php
// app/Http/Controllers/Admin/MatiereController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Matiere;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MatiereController extends Controller
{
    public function uploadPhoto(Request $r, Matiere $matiere)
    {
        $r->validate([
            'photo' => 'required|image|max:4096', // jpg/png/webp <= 4 Mo
        ]);

        $path = $r->file('photo')->store('matieres', 'public'); // storage/app/public/matieres/...
        $url  = Storage::url($path); // => /storage/matieres/xxx.jpg

        $matiere->update(['photo' => $url]);

        return response()->json(['success' => true, 'data' => ['photo' => $url]]);
    }
}
