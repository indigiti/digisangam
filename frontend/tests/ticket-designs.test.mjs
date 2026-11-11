import test from 'node:test'
import assert from 'node:assert/strict'
import {TICKET_DESIGNS,getTicketDesign,DEFAULT_TICKET_DESIGN} from '../src/services/ticketDesigns.js'

test('five independent, usable ticket themes are available',()=>{
  assert.equal(TICKET_DESIGNS.length,5)
  assert.equal(new Set(TICKET_DESIGNS.map(theme=>theme.id)).size,5)
  for(const theme of TICKET_DESIGNS){
    assert.match(theme.accent,/^#[0-9a-fA-F]{6}$/)
    assert.ok(theme.name&&theme.description&&theme.hero)
    assert.equal(getTicketDesign(theme.id),theme)
  }
})
test('unknown and legacy missing themes fall back safely',()=>{
  assert.equal(getTicketDesign('not-an-option').id,DEFAULT_TICKET_DESIGN)
  assert.equal(getTicketDesign(undefined).id,DEFAULT_TICKET_DESIGN)
})
