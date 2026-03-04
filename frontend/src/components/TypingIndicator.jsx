import React from 'react'
import { cn } from '../lib/utils'

const TypingIndicator = ({ className }) => {
  return (
    <div className={cn("flex items-center space-x-1 px-4 py-2", className)}>
      <div className="flex space-x-1">
        <div className="w-2 h-2 bg-muted-foreground rounded-full animate-pulse"></div>
        <div className="w-2 h-2 bg-muted-foreground rounded-full animate-pulse delay-75"></div>
        <div className="w-2 h-2 bg-muted-foreground rounded-full animate-pulse delay-150"></div>
      </div>
      <span className="text-xs text-muted-foreground ml-2">Bot is typing...</span>
    </div>
  )
}

export default TypingIndicator
