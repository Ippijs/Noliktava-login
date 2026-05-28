param(
    [string]$Remote = 'git@github.com:Ippijs/Noliktava.git',
    [string]$Branch = 'main',
    [string]$Message = 'Update: responsive mobile layout and fixes'
)

Write-Host "Starting push to $Remote on branch $Branch"

if (-not (Test-Path .git)) {
    Write-Host "No git repo found — initializing..."
    git init
}

# Ensure .gitignore exists
if (-not (Test-Path .gitignore)) {
    Write-Host "Creating default .gitignore"
    @"
# Auto-generated .gitignore
vendor/
node_modules/
.env
.env.*
*.log
*.sql
/.vscode/
/.idea/
.DS_Store
"@> .gitignore
}

git add -A

$staged = git status --porcelain
if (-not $staged) {
    Write-Host "No changes to commit."
} else {
    git commit -m "$Message"
}

# Add or update remote
$existing = git remote get-url origin 2>$null
if ($LASTEXITCODE -eq 0) {
    Write-Host "Remote 'origin' exists. Updating URL to $Remote"
    git remote set-url origin $Remote
} else {
    Write-Host "Adding remote origin $Remote"
    git remote add origin $Remote
}

# Create branch locally and push
git branch --show-current | Out-Null
if ((git rev-parse --abbrev-ref HEAD) -ne $Branch) {
    git branch -M $Branch
}

Write-Host "Pushing to origin/$Branch..."
$push = git push -u origin $Branch
if ($LASTEXITCODE -ne 0) {
    Write-Host "Push failed. Attempting to pull/rebase and push again..."
    git pull --rebase origin $Branch
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Pull failed. Resolve remote conflicts manually, then run the script again."
        exit 1
    }
    git push -u origin $Branch
}

Write-Host "Done. Repo pushed to $Remote (branch $Branch)."
