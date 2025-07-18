<#
.SYNOPSIS
    Affiche l'arborescence d'un répertoire avec les fichiers.
.DESCRIPTION
    Parcourt récursivement un dossier et affiche sa structure sous forme d'arbre.
#>

param (
    [string]$Path = ".",  # Chemin par défaut : répertoire courant
    [int]$Depth = -1      # Profondeur infinie par défaut
)

function Get-Tree {
    param (
        [string]$Folder,
        [string]$Indent = "",
        [int]$CurrentDepth = 0
    )

    # Affiche le nom du dossier courant
    Write-Output ("{0}{1}" -f $Indent, (Split-Path $Folder -Leaf))

    # Limite la profondeur si spécifiée
    if ($Depth -ne -1 -and $CurrentDepth -ge $Depth) { return }

    # Parcourt les sous-dossiers
    $SubFolders = Get-ChildItem -Path $Folder -Directory -ErrorAction SilentlyContinue
    foreach ($SubFolder in $SubFolders) {
        Get-Tree -Folder $SubFolder.FullName -Indent ($Indent + "|   ") -CurrentDepth ($CurrentDepth + 1)
    }

    # Affiche les fichiers
    $Files = Get-ChildItem -Path $Folder -File -ErrorAction SilentlyContinue
    foreach ($File in $Files) {
        Write-Output ("{0}+-- {1}" -f $Indent, $File.Name)
    }
}

# Vérifie que le chemin existe
if (-not (Test-Path -Path $Path -PathType Container)) {
    Write-Error "Le chemin '$Path' n'existe pas ou n'est pas un dossier."
    exit 1
}

# Exécute la fonction
Write-Output "Arborescence de '$((Get-Item $Path).FullName)':"
Get-Tree -Folder (Get-Item $Path).FullName