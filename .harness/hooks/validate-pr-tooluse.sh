#!/bin/bash
# Hook: preToolUse (Cursor) / tool-guard (genérico)
# Intercepta chamadas de tool que envolvam criação de PR
# e injeta contexto para o agente seguir o template.
# Protocolo: recebe JSON via stdin, retorna JSON via stdout.

input=$(cat)
tool_name=$(echo "$input" | jq -r '.tool_name // empty')
tool_input=$(echo "$input" | jq -r '.input // empty')

is_pr_related() {
  if [[ "$tool_name" == "Shell" ]]; then
    local cmd=$(echo "$tool_input" | jq -r '.command // empty' 2>/dev/null)
    [[ "$cmd" =~ gh[[:space:]]+pr[[:space:]]+create ]] && return 0
    [[ "$cmd" =~ git[[:space:]]+push ]] && return 0
  fi
  return 1
}

if ! is_pr_related; then
  echo '{"permission": "allow"}'
  exit 0
fi

cmd=$(echo "$tool_input" | jq -r '.command // empty' 2>/dev/null)

if [[ "$cmd" =~ gh[[:space:]]+pr[[:space:]]+create ]]; then
  cat <<RESPONSE_JSON
{
  "permission": "ask",
  "user_message": "Um PR esta sendo criado. Verificando conformidade com o template obrigatorio...",
  "agent_message": "ATENCAO: Antes de criar este PR, verifique se o body contem TODAS as secoes obrigatorias: 1) Contexto, 2) Objetivo, 3) Criterios de Aceite (min. 2 itens), 4) Como Testar Manualmente (min. 2 passos), 5) Cenarios de Teste (min. 2 cenarios). Se alguma secao estiver faltando, NAO execute o comando. Peca ao usuario para completar. Consulte a rule pr-template para o formato correto."
}
RESPONSE_JSON
  exit 0
fi

if [[ "$cmd" =~ git[[:space:]]+push ]]; then
  cat <<RESPONSE_JSON
{
  "permission": "ask",
  "user_message": "Push detectado. Se ha um PR associado, certifique-se de que o template foi seguido.",
  "agent_message": "Um git push foi detectado. Se este push esta associado a uma criacao de PR, verifique se o PR correspondente segue o template obrigatorio com todas as 5 secoes: Contexto, Objetivo, Criterios de Aceite, Como Testar Manualmente e Cenarios de Teste."
}
RESPONSE_JSON
  exit 0
fi

echo '{"permission": "allow"}'
exit 0
