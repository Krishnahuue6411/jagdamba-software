param(
  [Parameter(Mandatory = $true)]
  [string]$Payload
)

$ErrorActionPreference = "Continue"

function Decode-Payload {
  try {
    $json = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($Payload))
    return $json | ConvertFrom-Json
  } catch {
    throw "Failed to decode payload: $_"
  }
}

function New-Connection {
  param($dbPath)
  
  if (-not (Test-Path $dbPath)) {
    throw "Database file not found: $dbPath"
  }
  
  $providers = @(
    "Microsoft.ACE.OLEDB.16.0",
    "Microsoft.ACE.OLEDB.12.0"
  )
  
  $lastError = $null
  foreach ($provider in $providers) {
    try {
      $connectionString = "Provider=$provider;Data Source=$dbPath;Persist Security Info=False;"
      $connection = New-Object System.Data.OleDb.OleDbConnection($connectionString)
      $connection.Open()
      return $connection
    } catch {
      $lastError = $_
      continue
    }
  }
  
  throw "Failed to connect to database. Please install Microsoft Access Database Engine. Last error: $lastError"
}

function Get-Tables {
  param($connection)
  $tables = @()
  try {
    $schema = $connection.GetSchema("Tables")
    $tables = $schema | Where-Object { 
      $_.TABLE_TYPE -eq "TABLE" -and $_.TABLE_NAME -notlike "MSys*" 
    } | Select-Object -ExpandProperty TABLE_NAME
  } catch {
    throw "Failed to get tables: $_"
  }
  return $tables
}

function Get-Columns {
  param($connection, $table)
  try {
    $cmd = $connection.CreateCommand()
    $cmd.CommandText = "SELECT TOP 1 * FROM [$table]"
    $reader = $cmd.ExecuteReader()
    try {
      $schema = $reader.GetSchemaTable()
      $columns = @()
      if ($schema) {
        $columns = $schema | Sort-Object ColumnOrdinal | ForEach-Object { $_.ColumnName }
      }
      return $columns
    } finally {
      $reader.Close()
    }
  } catch {
    throw "Failed to get columns for table '$table': $_"
  }
}

function Add-Param {
  param($cmd, $value)
  $param = $cmd.CreateParameter()
  $param.ParameterName = "?"
  $param.DbType = [System.Data.DbType]::Object
  if ($null -eq $value -or $value -eq "") {
    $param.Value = [DBNull]::Value
  } else {
    $param.Value = $value
  }
  $cmd.Parameters.Add($param) | Out-Null
}

function Read-Rows {
  param($connection, $sql)
  try {
    $cmd = $connection.CreateCommand()
    $cmd.CommandText = $sql
    $adapter = New-Object System.Data.OleDb.OleDbDataAdapter($cmd)
    $table = New-Object System.Data.DataTable
    [void]$adapter.Fill($table)
    
    $rows = @()
    foreach ($dr in $table.Rows) {
      $obj = [ordered]@{}
      foreach ($col in $table.Columns) {
        $value = $dr[$col.ColumnName]
        if ($value -is [DBNull]) { $value = $null }
        if ($value -is [DateTime]) { $value = $value.ToString("yyyy-MM-dd") }
        $obj[$col.ColumnName] = $value
      }
      $rows += [pscustomobject]$obj
    }
    return $rows
  } catch {
    throw "Failed to read rows: $_"
  }
}

function Where-Clause {
  param($cmd, $columns, $original)
  $parts = @()
  foreach ($col in $columns) {
    $value = $original.$col
    if ($null -eq $value -or $value -eq "") {
      $parts += "([$col] IS NULL OR [$col] = '')"
    } else {
      $parts += "[$col] = ?"
      Add-Param $cmd $value
    }
  }
  if ($parts.Count -eq 0) { throw "No original row supplied." }
  return ($parts -join " AND ")
}

# Main execution
try {
  $request = Decode-Payload
  $dbPath = $request.dbPath
  
  if (-not $dbPath) {
    throw "No database path provided"
  }
  
  if (-not [System.IO.Path]::IsPathRooted($dbPath)) {
    $dbPath = Join-Path $PSScriptRoot $dbPath
  }
  
  $cn = New-Connection $dbPath
  
  try {
    switch ($request.action) {
      "meta" {
        $tables = @()
        foreach ($name in Get-Tables $cn) {
          $countCmd = $cn.CreateCommand()
          $countCmd.CommandText = "SELECT COUNT(*) FROM [$name]"
          $count = 0
          try {
            $count = [int]$countCmd.ExecuteScalar()
          } catch { $count = 0 }
          
          $columns = @()
          try {
            $columns = @(Get-Columns $cn $name)
          } catch { $columns = @() }
          
          $tables += [pscustomobject]@{
            name = $name
            count = $count
            columns = $columns
          }
        }
        $result = @{ ok = $true; tables = $tables }
        Write-Host ($result | ConvertTo-Json -Depth 12 -Compress)
      }
      
      "list" {
        $table = [string]$request.table
        if (-not $table) { throw "Table name required" }
        
        $limit = [int]($(if ($request.limit) { $request.limit } else { 500 }))
        $columns = @(Get-Columns $cn $table)
        $rows = @(Read-Rows $cn "SELECT TOP $limit * FROM [$table]")
        $result = @{ ok = $true; table = $table; columns = $columns; rows = $rows }
        Write-Host ($result | ConvertTo-Json -Depth 12 -Compress)
      }
      
      "insert" {
        $table = [string]$request.table
        $columns = @(Get-Columns $cn $table)
        $row = $request.row
        
        $fields = @($columns | Where-Object { 
          $row.PSObject.Properties.Name -contains $_ -and 
          $null -ne $row.$_ -and 
          "$($row.$_)".Trim() -ne "" 
        })
        
        if ($fields.Count -eq 0) { throw "No values supplied." }
        
        $cmd = $cn.CreateCommand()
        $fieldSql = ($fields | ForEach-Object { "[$_]" }) -join ", "
        $paramSql = (($fields | ForEach-Object { "?" }) -join ", ")
        $cmd.CommandText = "INSERT INTO [$table] ($fieldSql) VALUES ($paramSql)"
        
        foreach ($field in $fields) { Add-Param $cmd $row.$field }
        [void]$cmd.ExecuteNonQuery()
        Write-Host '{"ok":true}'
      }
      
      "update" {
        $table = [string]$request.table
        $columns = @(Get-Columns $cn $table)
        $row = $request.row
        $original = $request.original
        
        $fields = @($columns | Where-Object { $row.PSObject.Properties.Name -contains $_ })
        if ($fields.Count -eq 0) { throw "No update values supplied." }
        
        $cmd = $cn.CreateCommand()
        $setSql = ($fields | ForEach-Object { "[$_] = ?" }) -join ", "
        foreach ($field in $fields) { Add-Param $cmd $row.$field }
        
        $where = Where-Clause $cmd $columns $original
        $cmd.CommandText = "UPDATE [$table] SET $setSql WHERE $where"
        $affected = $cmd.ExecuteNonQuery()
        $result = @{ ok = $true; affected = $affected }
        Write-Host ($result | ConvertTo-Json -Depth 12 -Compress)
      }
      
      "delete" {
        $table = [string]$request.table
        $columns = @(Get-Columns $cn $table)
        $original = $request.original
        
        $cmd = $cn.CreateCommand()
        $where = Where-Clause $cmd $columns $original
        $cmd.CommandText = "DELETE FROM [$table] WHERE $where"
        $affected = $cmd.ExecuteNonQuery()
        $result = @{ ok = $true; affected = $affected }
        Write-Host ($result | ConvertTo-Json -Depth 12 -Compress)
      }
      
      default {
        throw "Unknown action: $($request.action)"
      }
    }
  } catch {
    $result = @{ ok = $false; error = $_.Exception.Message }
    Write-Host ($result | ConvertTo-Json -Depth 12 -Compress)
    exit 1
  } finally {
    $cn.Close()
  }
} catch {
  $result = @{ ok = $false; error = $_.Exception.Message }
  Write-Host ($result | ConvertTo-Json -Depth 12 -Compress)
  exit 1
}